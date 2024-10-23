<?php

namespace App\Services\Databackd\Reports;

use App\Services\Databackd\FinancialDataService;
use Illuminate\Support\Facades\DB;

/**
 * Pivot engine of the flexible reports. Aggregates flow entries (kind = flow, amount_base) in SQL.
 *
 * Config (see normalizeConfig() for defaults):
 *   rows              account|account_type|entity|region|client_segment|product_group|cost_center|counterparty
 *   columns           period | any dimension of the list above
 *   measure           sum|count|avg
 *   percent_of_total  bool  add the share of every cell/row in the grand total
 *   show_change       bool  add the % change of every period column vs the previous column (columns = period)
 *   account_type      all|revenue|expense   restrict to revenue or expense accounts
 *   account_codes     string[]              restrict to these account codes (e.g. ['604', '605'])
 *   limit             int   keep the top N rows by total, the rest is merged into the row "other" (0 = all)
 *
 * Revenue and expense are both positive numbers (sign convention of FinancialDataService).
 */
class ReportBuilder
{

    const ROW_DIMENSIONS = ['account', 'account_type', 'entity', 'region', 'client_segment', 'product_group', 'cost_center', 'counterparty'];
    const MEASURES = ['sum', 'count', 'avg'];

    const OTHER_KEY = '__other';
    const NONE_KEY = '__none';

    protected FinancialDataService $data;

    public function __construct(?FinancialDataService $data = null)
    {
        $this->data = $data ?? new FinancialDataService();
    }

    public static function normalizeConfig(array $config): array
    {
        $rows = in_array($config['rows'] ?? '', self::ROW_DIMENSIONS, true) ? $config['rows'] : 'account';
        $columns = $config['columns'] ?? 'period';
        $columns = ($columns === 'period' || in_array($columns, self::ROW_DIMENSIONS, true)) ? $columns : 'period';

        return [
            'rows' => $rows,
            'columns' => $columns,
            'measure' => in_array($config['measure'] ?? '', self::MEASURES, true) ? $config['measure'] : 'sum',
            'percent_of_total' => filter_var($config['percent_of_total'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'show_change' => filter_var($config['show_change'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'account_type' => in_array($config['account_type'] ?? '', ['revenue', 'expense'], true) ? $config['account_type'] : 'all',
            'account_codes' => array_values(array_filter(array_map('strval', (array)($config['account_codes'] ?? [])))),
            'limit' => max(0, (int)($config['limit'] ?? 0)),
            'chart' => in_array($config['chart'] ?? '', ['bar', 'line', 'pie'], true) ? $config['chart'] : 'bar',
        ];
    }

    /**
     * Pivot table.
     *
     * @return array{
     *   columns: array<int, array{key: string, label: string, from?: \Carbon\Carbon, to?: \Carbon\Carbon}>,
     *   rows: array<int, array{key: string, label: string, values: array<string, float>, total: float, share?: float, pct?: array<string, float>, change?: array<string, float|null>}>,
     *   totals: array{key: string, label: string, values: array<string, float>, total: float, pct?: array<string, float>, change?: array<string, float|null>},
     *   config: array
     * }
     */
    public function pivot(array $config, ReportFilters $f): array
    {
        $config = self::normalizeConfig($config);
        $periodColumns = $config['columns'] === 'period';

        $rowDef = $this->dimension($config['rows']);
        $q = $this->data->query($f->toServiceFilters())
            ->join('fin_accounts', 'fin_accounts.id', '=', 'fin_entries.account_id')
            ->where('fin_entries.kind', 'flow')
            ->whereBetween('fin_entries.entry_date', [$f->from->toDateString(), $f->to->toDateString()]);

        if ($config['account_type'] !== 'all') {
            $q->where('fin_accounts.type', $config['account_type']);
        } else {
            $q->whereIn('fin_accounts.type', ['revenue', 'expense']);
        }

        if ($config['account_codes']) {
            $q->whereIn('fin_accounts.code', $config['account_codes']);
        }

        $rowKeyExpr = $rowDef['key'];
        $selects = ["$rowKeyExpr as row_key", 'SUM(fin_entries.amount_base) as s', 'COUNT(*) as c'];
        $groups = [$rowKeyExpr];

        if ($periodColumns) {
            $colKeyExpr = PeriodBuckets::sqlExpression($f->periodicity, 'fin_entries.entry_date');
        } else {
            $colKeyExpr = $this->dimension($config['columns'])['key'];
        }

        $selects[] = "$colKeyExpr as col_key";
        $groups[] = $colKeyExpr;

        $data = $q->selectRaw(implode(', ', $selects))->groupByRaw(implode(', ', $groups))->get();

        // cells[row][col] = [sum, count]
        $cells = [];
        $rowTotals = [];
        $colTotals = [];
        $grand = ['s' => 0.0, 'c' => 0];

        foreach ($data as $r) {
            $row = $r->row_key === null || $r->row_key === '' ? self::NONE_KEY : (string)$r->row_key;
            $col = $r->col_key === null || $r->col_key === '' ? self::NONE_KEY : (string)$r->col_key;
            $s = (float)$r->s;
            $c = (int)$r->c;

            $cells[$row][$col] = [$s, $c];
            $rowTotals[$row] = [($rowTotals[$row][0] ?? 0) + $s, ($rowTotals[$row][1] ?? 0) + $c];
            $colTotals[$col] = [($colTotals[$col][0] ?? 0) + $s, ($colTotals[$col][1] ?? 0) + $c];
            $grand['s'] += $s;
            $grand['c'] += $c;
        }

        // Columns
        if ($periodColumns) {
            $columns = array_map(fn($b) => ['key' => $b['key'], 'label' => $b['label'], 'from' => $b['from'], 'to' => $b['to']], $f->buckets());
        } else {
            $columns = $this->dimensionColumns($config['columns'], $colTotals);
        }

        // Rows (labels, ordering)
        $labels = $this->labels($config['rows'], array_keys($rowTotals));
        $rowKeys = array_keys($rowTotals);
        $this->sortRowKeys($config['rows'], $rowKeys, $rowTotals, $labels);

        $other = null;

        if ($config['limit'] > 0 && count($rowKeys) > $config['limit']) {
            $keep = array_slice($rowKeys, 0, $config['limit']);
            $rest = array_slice($rowKeys, $config['limit']);
            $other = $rest;
            $rowKeys = $keep;
        }

        $measure = $config['measure'];
        $value = fn(array $cell) => $this->measureValue($measure, $cell[0], $cell[1]);
        $grandValue = $value([$grand['s'], $grand['c']]);

        $outRows = [];
        $make = function (string $key, string $label, array $rowCells, array $rowTotal) use ($columns, $value, $config, $grandValue) {
            $values = [];
            $pct = [];

            foreach ($columns as $col) {
                $cell = $rowCells[$col['key']] ?? [0.0, 0];
                $values[$col['key']] = $value($cell);

                if ($config['percent_of_total']) {
                    $pct[$col['key']] = $grandValue ? round($values[$col['key']] / $grandValue * 100, 2) : 0.0;
                }
            }

            $row = ['key' => $key, 'label' => $label, 'values' => $values, 'total' => $value($rowTotal)];

            if ($config['percent_of_total']) {
                $row['pct'] = $pct;
                $row['share'] = $grandValue ? round($row['total'] / $grandValue * 100, 2) : 0.0;
            }

            if ($config['show_change'] && $config['columns'] === 'period') {
                $row['change'] = $this->changes($columns, $values);
            }

            return $row;
        };

        foreach ($rowKeys as $key) {
            $outRows[] = $make((string)$key, $labels[$key] ?? (string)$key, $cells[$key] ?? [], $rowTotals[$key]);
        }

        if ($other) {
            $otherCells = [];
            $otherTotal = [0.0, 0];

            foreach ($other as $key) {
                foreach ($cells[$key] as $col => $cell) {
                    $otherCells[$col] = [($otherCells[$col][0] ?? 0) + $cell[0], ($otherCells[$col][1] ?? 0) + $cell[1]];
                }

                $otherTotal = [$otherTotal[0] + $rowTotals[$key][0], $otherTotal[1] + $rowTotals[$key][1]];
            }

            $outRows[] = $make(self::OTHER_KEY, __('databackd-reports-common.other'), $otherCells, $otherTotal);
        }

        $totals = $make('total', __('databackd-reports-common.total'), $colTotals, [$grand['s'], $grand['c']]);
        unset($totals['share']);

        return ['columns' => $columns, 'rows' => $outRows, 'totals' => $totals, 'config' => $config];
    }

    /**
     * Same pivot for the comparison period of the filters (compare = previous|last_year), with the change per row.
     *
     * @return array{period: array{from: \Carbon\Carbon, to: \Carbon\Carbon}, pivot: array, deltas: array<string, array{current: float, previous: float, change: float, change_pct: float|null}>, totals: array{current: float, previous: float, change: float, change_pct: float|null}}|null
     */
    public function compare(array $config, ReportFilters $f): ?array
    {
        $period = $f->comparePeriod();

        if (!$period) {
            return null;
        }

        $current = $this->pivot($config, $f);
        $previous = $this->pivot($config, $f->withRange($period['from'], $period['to']));

        $prevByKey = collect($previous['rows'])->keyBy('key');
        $deltas = [];

        foreach ($current['rows'] as $row) {
            $p = (float)($prevByKey[$row['key']]['total'] ?? 0);
            $deltas[$row['key']] = $this->delta($row['total'], $p);
        }

        foreach ($previous['rows'] as $row) {
            if (!isset($deltas[$row['key']])) {
                $deltas[$row['key']] = $this->delta(0.0, $row['total']);
            }
        }

        return [
            'period' => $period,
            'pivot' => $previous,
            'deltas' => $deltas,
            'totals' => $this->delta($current['totals']['total'], $previous['totals']['total']),
        ];
    }

    /** % change from previous to current; null when previous is 0. */
    public static function changePct(float $current, float $previous): ?float
    {
        return abs($previous) < 0.005 ? null : round(($current - $previous) / abs($previous) * 100, 1);
    }

    protected function delta(float $current, float $previous): array
    {
        return [
            'current' => $current, 'previous' => $previous,
            'change' => round($current - $previous, 2), 'change_pct' => self::changePct($current, $previous),
        ];
    }

    protected function changes(array $columns, array $values): array
    {
        $out = [];
        $prev = null;

        foreach ($columns as $col) {
            $out[$col['key']] = $prev === null ? null : self::changePct($values[$col['key']], $prev);
            $prev = $values[$col['key']];
        }

        return $out;
    }

    protected function measureValue(string $measure, float $sum, int $count): float
    {
        switch ($measure) {
            case 'count':
                return (float)$count;
            case 'avg':
                return $count ? round($sum / $count, 2) : 0.0;
            case 'sum':
            default:
                return round($sum, 2);
        }
    }

    /** @return array{key: string} SQL expression of the dimension key */
    protected function dimension(string $dim): array
    {
        switch ($dim) {
            case 'account':
                return ['key' => 'fin_accounts.code'];
            case 'account_type':
                return ['key' => 'fin_accounts.type'];
            case 'entity':
                return ['key' => 'fin_entries.entity_id'];
            case 'region':
                return ['key' => 'fin_entries.region_id'];
            case 'client_segment':
                return ['key' => 'fin_entries.client_segment_id'];
            case 'product_group':
                return ['key' => 'fin_entries.product_group_id'];
            case 'cost_center':
                return ['key' => 'fin_entries.cost_center_id'];
            case 'counterparty':
                return ['key' => 'fin_entries.counterparty_name'];
        }

        throw new \InvalidArgumentException('Unknown dimension: ' . $dim);
    }

    /** Labels (key => label) for the distinct keys of a dimension. */
    protected function labels(string $dim, array $keys): array
    {
        $companyId = $this->data->companyId();
        $none = __('databackd-reports-common.dimension_none');
        $labels = [self::NONE_KEY => $none];
        $ids = array_filter($keys, fn($k) => $k !== self::NONE_KEY);

        switch ($dim) {
            case 'account':
                foreach (DB::table('fin_accounts')->where('company_id', $companyId)->get(['code', 'name']) as $a) {
                    $labels[$a->code] = $a->code . ' ' . $a->name;
                }
                break;
            case 'account_type':
                foreach ($ids as $k) {
                    $labels[$k] = __('databackd-reports-common.account_types.' . $k);
                }
                break;
            case 'entity':
                $labels += DB::table('fin_entities')->where('company_id', $companyId)->pluck('name', 'id')->all();
                break;
            case 'region':
            case 'client_segment':
            case 'product_group':
            case 'cost_center':
                $labels += DB::table('fin_dimensions')->where('company_id', $companyId)->where('type', $dim)->pluck('name', 'id')->all();
                break;
            case 'counterparty':
                foreach ($ids as $k) {
                    $labels[$k] = $k;
                }
                break;
        }

        return $labels;
    }

    protected function sortRowKeys(string $dim, array &$keys, array $totals, array $labels): void
    {
        if ($dim === 'account') {
            sort($keys, SORT_STRING);
        } elseif ($dim === 'account_type') {
            $order = ['revenue' => 0, 'expense' => 1];
            usort($keys, fn($a, $b) => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));
        } else {
            // "not set" always last, the rest by total descending
            usort($keys, function ($a, $b) use ($totals) {
                if ($a === self::NONE_KEY || $b === self::NONE_KEY) {
                    return $a === self::NONE_KEY ? 1 : -1;
                }

                return $totals[$b][0] <=> $totals[$a][0];
            });
        }
    }

    /** Columns of a dimension: only values with data, by total descending, "not set" last. */
    protected function dimensionColumns(string $dim, array $colTotals): array
    {
        $keys = array_keys($colTotals);
        $this->sortRowKeys($dim, $keys, $colTotals, []);
        $labels = $this->labels($dim, $keys);

        return array_map(fn($k) => ['key' => (string)$k, 'label' => $labels[$k] ?? (string)$k], $keys);
    }

}
