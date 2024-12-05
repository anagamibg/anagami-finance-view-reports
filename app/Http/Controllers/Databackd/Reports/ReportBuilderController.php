<?php

namespace App\Http\Controllers\Databackd\Reports;

use App\Http\Controllers\AccountBaseController;
use App\Services\Databackd\Reports\ReportBuilder;
use App\Services\Databackd\Reports\ReportFilters;
use Illuminate\Http\Request;

/**
 * Flexible reports (pivot builder). The whole state lives in the query string, so a saved report can be
 * reopened with a plain redirect: rows, columns, measure, percent_of_total, show_change, account_type,
 * account_codes[], limit, chart + the parameters of ReportFilters.
 */
class ReportBuilderController extends AccountBaseController
{

    /** Default report: revenue by region by month (the filters default to the last 12 months, monthly). */
    const DEFAULTS = ['rows' => 'region', 'columns' => 'period', 'measure' => 'sum', 'account_type' => 'revenue', 'chart' => 'bar'];

    const CONFIG_KEYS = ['rows', 'columns', 'measure', 'percent_of_total', 'show_change', 'account_type', 'account_codes', 'limit', 'chart'];

    /** Rendered table limits (the chart and the totals always use all the data). */
    const MAX_COLUMNS = 366;
    const MAX_ROWS = 300;
    const MANY_COLUMNS = 60;
    const CHART_SERIES = 12;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            // Set here (not in the constructor body) so the title uses the locale resolved by the parent middleware
            $this->pageTitle = __('databackd-reports-builder.title');
            abort_403(in_array('client', user_roles()));

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $filters = ReportFilters::fromRequest($request);
        // Without any builder parameter (first visit, link from the dashboard) the default report is shown;
        // otherwise missing parameters fall back to the ReportBuilder defaults.
        $hasConfig = $request->hasAny(['rows', 'columns', 'measure', 'account_type', 'account_codes']);
        $config = ReportBuilder::normalizeConfig($hasConfig ? $request->only(self::CONFIG_KEYS) : array_merge(self::DEFAULTS, $request->only(['chart'])));

        $builder = new ReportBuilder();
        $pivot = $builder->pivot($config, $filters);
        $compare = $builder->compare($config, $filters);

        $columns = $pivot['columns'];
        $rows = $pivot['rows'];
        $notices = $this->notices($config, $pivot, $filters);

        $this->filters = $filters;
        $this->config = $config;
        $this->pivot = $pivot;
        $this->compare = $compare;
        $this->hasData = collect($rows)->contains(fn($r) => $r['total'] != 0) || $pivot['totals']['total'] != 0;
        $this->tableColumns = array_slice($columns, 0, self::MAX_COLUMNS);
        $this->tableRows = array_slice($rows, 0, self::MAX_ROWS);
        $this->notices = $notices;
        $this->maxCell = $this->maxCell($this->tableRows, $this->tableColumns);
        $this->chartData = $this->chartData($config, $pivot);
        $this->chartSeriesLimit = self::CHART_SERIES;

        // Full effective configuration: the export screens get exactly what is shown here (also for the defaults).
        $this->exportQuery = array_merge($this->configQuery($config), $filters->toQuery());
        $this->saveConfig = array_merge($config, ['filters' => $filters->toQuery()]);

        $this->accounts = \App\Models\Databackd\FinAccount::whereIn('type', ['revenue', 'expense'])->orderBy('code')->get(['code', 'name', 'type']);
        $this->rowDimensions = ReportBuilder::ROW_DIMENSIONS;
        $this->resetUrl = route('databackd.reports.builder.index');

        return view('databackd.reports.builder.index', $this->data);
    }

    /** Query string form of a normalized config (booleans as 1/0). */
    protected function configQuery(array $config): array
    {
        $q = $config;
        $q['percent_of_total'] = $config['percent_of_total'] ? 1 : 0;
        $q['show_change'] = $config['show_change'] ? 1 : 0;

        if (!$q['account_codes']) {
            unset($q['account_codes']);
        }

        return $q;
    }

    /** Messages for combinations without much sense and for very wide/long results. */
    protected function notices(array $config, array $pivot, ReportFilters $filters): array
    {
        $notices = [];
        $dimensionLabel = fn($d) => __('databackd-reports-common.dimensions.' . $d);
        $columnCount = count($pivot['columns']);
        $rowCount = count($pivot['rows']);

        if ($config['rows'] === $config['columns']) {
            $notices[] = ['warning', __('databackd-reports-builder.notices.same_dimension')];
        }

        $noExpense = ['region', 'client_segment'];
        $noRevenue = ['cost_center'];

        foreach (array_unique([$config['rows'], $config['columns']]) as $dim) {
            if ($config['account_type'] === 'expense' && in_array($dim, $noExpense, true)) {
                $notices[] = ['info', __('databackd-reports-builder.notices.expense_no_dimension', ['dimension' => $dimensionLabel($dim)])];
            }

            if ($config['account_type'] === 'revenue' && in_array($dim, $noRevenue, true)) {
                $notices[] = ['info', __('databackd-reports-builder.notices.revenue_no_dimension', ['dimension' => $dimensionLabel($dim)])];
            }
        }

        // S12: revenue + expense in one sum is a turnover, not a result - say so unless the rows/columns split them
        $splitsTypes = array_intersect([$config['rows'], $config['columns']], ['account', 'account_type']);

        if ($config['account_type'] === 'all' && $config['measure'] === 'sum' && !$splitsTypes) {
            $notices[] = ['info', __('databackd-reports-builder.notices.mixed_types')];
        }

        // S12: PeriodBuckets stops at MAX_BUCKETS columns - the later periods would silently be missing from the table/chart
        if ($config['columns'] === 'period' && $pivot['columns']) {
            $lastBucket = end($pivot['columns']);

            if ($lastBucket['to']->copy()->startOfDay()->lt($filters->to->copy()->startOfDay())) {
                $notices[] = ['warning', __('databackd-reports-builder.notices.period_too_long', [
                    'n' => $columnCount, 'date' => $lastBucket['to']->format('d.m.Y'),
                ])];
            }
        }

        if ($config['show_change'] && $config['columns'] !== 'period') {
            $notices[] = ['info', __('databackd-reports-builder.notices.change_needs_period')];
        }

        if ($config['percent_of_total'] && $config['measure'] === 'avg') {
            $notices[] = ['info', __('databackd-reports-builder.notices.avg_percent')];
        }

        if ($columnCount > self::MANY_COLUMNS) {
            $notices[] = ['warning', __('databackd-reports-builder.notices.many_columns', ['n' => $columnCount])];
        }

        if ($columnCount > self::MAX_COLUMNS) {
            $notices[] = ['warning', __('databackd-reports-builder.notices.columns_truncated', ['shown' => self::MAX_COLUMNS, 'n' => $columnCount])];
        }

        if ($rowCount > self::MAX_ROWS) {
            $notices[] = ['warning', __('databackd-reports-builder.notices.rows_truncated', ['shown' => self::MAX_ROWS, 'n' => $rowCount])];
        }

        return $notices;
    }

    /** Biggest single cell, for the colour intensity of the table. */
    protected function maxCell(array $rows, array $columns): float
    {
        $max = 0.0;

        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $max = max($max, (float)($row['values'][$col['key']] ?? 0));
            }
        }

        return $max;
    }

    /**
     * Data of the chart: line/bar series are the top rows by total over all the columns; the pie slices are the
     * row totals (the rest in "other").
     */
    protected function chartData(array $config, array $pivot): array
    {
        $columns = $pivot['columns'];
        $sorted = collect($pivot['rows'])->sortByDesc('total')->values();
        $top = $sorted->take(self::CHART_SERIES);

        $series = $top->map(fn($r) => [
            'label' => $r['label'],
            'data' => array_map(fn($c) => (float)($r['values'][$c['key']] ?? 0), $columns),
        ])->all();

        $slices = $sorted->take(self::CHART_SERIES)->map(fn($r) => ['label' => $r['label'], 'value' => (float)$r['total']])->all();
        $rest = $sorted->slice(self::CHART_SERIES)->sum('total');

        if ($rest > 0 && $config['measure'] !== 'avg') {
            $slices[] = ['label' => __('databackd-reports-common.other'), 'value' => round((float)$rest, 2)];
        }

        return [
            'type' => $config['chart'],
            'measure' => $config['measure'],
            'unit' => __('databackd-reports-builder.units.' . $config['measure']),
            'labels' => array_map(fn($c) => $c['label'], $columns),
            'series' => $series,
            'slices' => $slices,
            'truncated' => $sorted->count() > self::CHART_SERIES,
        ];
    }

}
