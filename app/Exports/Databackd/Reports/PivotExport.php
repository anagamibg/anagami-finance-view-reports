<?php

namespace App\Exports\Databackd\Reports;

use App\Exports\Databackd\Concerns\NeutralizesFormulas;
use App\Helper\Files;
use App\Models\Company;
use App\Services\Databackd\FinancialDataService;
use App\Services\Databackd\Reports\ReportBuilder;
use App\Services\Databackd\Reports\ReportFilters;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel export of the pivot report (flexible reports) and the shared "export model" of Excel, PDF and print.
 *
 * Independent of the HTTP request and the session: everything comes from the constructor / prepare() arguments.
 *
 * prepare() returns:
 *   title, company_name, logo (data URI or URL or null), summary[] (filter lines), config_lines[], generated_at,
 *   filename, empty (bool), tables[] (see tables()).
 *
 * Table model (used by the Excel sheets and by the PDF / print blade partial):
 *   id (main|share|change|compare), title, subtitle, headers[], formats[] (text|money|int|pct|change per column),
 *   rows[] => ['cells' => [label, value, ...], 'total' => bool], chunkable (bool), trailing (number of columns at the
 *   right end that belong to the last chunk only, e.g. the "Total" column).
 */
class PivotExport implements WithMultipleSheets
{

    const NBSP = "\u{00A0}";

    public function __construct(protected array $config, protected ReportFilters $filters, protected int $companyId)
    {
    }

    /**
     * Everything the three exports show, computed once.
     */
    public static function prepare(array $config, ReportFilters $f, int $companyId): array
    {
        $config = ReportBuilder::normalizeConfig($config);
        $builder = new ReportBuilder(new FinancialDataService($companyId));
        $pivot = $builder->pivot($config, $f);
        $compare = $f->compare !== 'none' ? $builder->compare($config, $f) : null;
        $company = Company::find($companyId);
        $tz = $company?->timezone ?: config('app.timezone');

        $rowsLabel = __('databackd-reports-common.dimensions.' . $config['rows']);
        $colsLabel = __('databackd-reports-common.dimensions.' . $config['columns']);
        $title = __('databackd-reports-export.report_title', ['rows' => $rowsLabel, 'columns' => mb_strtolower($colsLabel)]);

        $lines = [__('databackd-reports-export.config.measure') . ': ' . __('databackd-reports-common.measures.' . $config['measure'])];

        if ($config['measure'] !== 'count') {
            $lines[] = __('databackd-reports-export.currency_note', ['currency' => __('databackd-reports-common.currency')]);
        }

        if ($config['account_type'] !== 'all') {
            $lines[] = __('databackd-reports-export.config.account_type') . ': ' . __('databackd-reports-common.account_types.' . $config['account_type']);
        }

        if ($config['account_codes']) {
            $lines[] = __('databackd-reports-export.config.accounts') . ': ' . implode(', ', $config['account_codes']);
        }

        if ($config['limit'] > 0) {
            $lines[] = __('databackd-reports-export.config.limit') . ': ' . __('databackd-reports-export.config.limit_top', ['n' => $config['limit']]);
        }

        $generated = Carbon::now($tz);

        return [
            'title' => $title,
            'company_name' => $company?->company_name ?? '',
            'logo' => self::logo($company),
            'summary' => $f->summary(),
            'config_lines' => $lines,
            'generated_at' => $generated->format('d.m.Y H:i'),
            'filename' => self::baseName($config, $generated),
            'empty' => count($pivot['rows']) === 0,
            'tables' => self::tables($pivot, $compare, $rowsLabel),
        ];
    }

    /** File name without extension, e.g. spravka-region-period-20261027 (ASCII). */
    public static function baseName(array $config, ?Carbon $date = null): string
    {
        $config = ReportBuilder::normalizeConfig($config);

        return __('databackd-reports-export.filename') . '-' . $config['rows'] . '-' . $config['columns'] . '-' . ($date ?? Carbon::now())->format('Ymd');
    }

    /**
     * @return array<int, array>
     */
    public static function tables(array $pivot, ?array $compare, string $rowsLabel): array
    {
        $config = $pivot['config'];
        $columns = $pivot['columns'];
        $keys = array_column($columns, 'key');
        $valueFormat = $config['measure'] === 'count' ? 'int' : 'money';
        $n = count($columns);
        $total = __('databackd-reports-common.total');
        $headers = array_merge([$rowsLabel], array_column($columns, 'label'), [$total]);
        $allRows = array_merge($pivot['rows'], [$pivot['totals']]);
        $lastIdx = count($allRows) - 1;
        $tables = [];

        $rows = [];
        foreach ($allRows as $i => $r) {
            $cells = [$r['label']];
            foreach ($keys as $k) {
                $cells[] = $r['values'][$k] ?? 0.0;
            }
            $cells[] = $r['total'];
            $rows[] = ['cells' => $cells, 'total' => $i === $lastIdx];
        }

        $tables[] = [
            'id' => 'main', 'title' => null, 'subtitle' => null, 'headers' => $headers,
            'formats' => array_merge(['text'], array_fill(0, $n + 1, $valueFormat)),
            'rows' => $rows, 'chunkable' => true, 'trailing' => 1,
        ];

        if ($config['percent_of_total'] && isset($pivot['totals']['pct'])) {
            $rows = [];
            foreach ($allRows as $i => $r) {
                $cells = [$r['label']];
                foreach ($keys as $k) {
                    $cells[] = $r['pct'][$k] ?? 0.0;
                }
                $cells[] = $i === $lastIdx ? 100.0 : ($r['share'] ?? 0.0);
                $rows[] = ['cells' => $cells, 'total' => $i === $lastIdx];
            }

            $tables[] = [
                'id' => 'share', 'title' => __('databackd-reports-export.tables.share'), 'subtitle' => null, 'headers' => $headers,
                'formats' => array_merge(['text'], array_fill(0, $n + 1, 'pct')),
                'rows' => $rows, 'chunkable' => true, 'trailing' => 1,
            ];
        }

        if ($config['show_change'] && $config['columns'] === 'period' && isset($pivot['totals']['change'])) {
            $rows = [];
            foreach ($allRows as $i => $r) {
                $cells = [$r['label']];
                foreach ($keys as $k) {
                    $cells[] = $r['change'][$k] ?? null;
                }
                $rows[] = ['cells' => $cells, 'total' => $i === $lastIdx];
            }

            $tables[] = [
                'id' => 'change', 'title' => __('databackd-reports-export.tables.change'), 'subtitle' => null,
                'headers' => array_slice($headers, 0, $n + 1),
                'formats' => array_merge(['text'], array_fill(0, $n, 'change')),
                'rows' => $rows, 'chunkable' => true, 'trailing' => 0,
            ];
        }

        if ($compare) {
            $tables[] = self::compareTable($pivot, $compare, $rowsLabel, $valueFormat);
        }

        return $tables;
    }

    protected static function compareTable(array $pivot, array $compare, string $rowsLabel, string $valueFormat): array
    {
        $labels = [];
        foreach (array_merge($compare['pivot']['rows'], $pivot['rows']) as $r) {
            $labels[$r['key']] = $r['label'];
        }

        $rows = [];
        $order = array_merge(array_column($pivot['rows'], 'key'), array_diff(array_column($compare['pivot']['rows'], 'key'), array_column($pivot['rows'], 'key')));

        foreach ($order as $key) {
            $d = $compare['deltas'][$key] ?? null;

            if ($d) {
                $rows[] = ['cells' => [$labels[$key] ?? $key, $d['current'], $d['previous'], $d['change'], $d['change_pct']], 'total' => false];
            }
        }

        $t = $compare['totals'];
        $rows[] = ['cells' => [__('databackd-reports-common.total'), $t['current'], $t['previous'], $t['change'], $t['change_pct']], 'total' => true];

        $period = $compare['period']['from']->format('d.m.Y') . ' – ' . $compare['period']['to']->format('d.m.Y');

        return [
            'id' => 'compare', 'title' => __('databackd-reports-export.tables.compare', ['period' => $period]), 'subtitle' => null,
            'headers' => [
                $rowsLabel, __('databackd-reports-export.compare.current'), __('databackd-reports-export.compare.previous'),
                __('databackd-reports-export.compare.change'), __('databackd-reports-export.compare.change_pct'),
            ],
            'formats' => ['text', $valueFormat, $valueFormat, $valueFormat, 'change'],
            'rows' => $rows, 'chunkable' => false, 'trailing' => 0,
        ];
    }

    /**
     * Splits a wide table into blocks of ~$size columns with the first (label) column repeated. Tables with up to
     * $max data columns stay in one piece. The trailing columns (Total) go to the last block.
     *
     * @return array<int, array>
     */
    public static function chunk(array $table, int $max = 14, int $size = 12): array
    {
        $trailing = $table['trailing'] ?? 0;
        $dataCols = count($table['headers']) - 1 - $trailing;

        if (!($table['chunkable'] ?? false) || $dataCols <= $max) {
            return [$table + ['part' => 1, 'parts' => 1]];
        }

        $parts = (int)ceil($dataCols / $size);
        $per = (int)ceil($dataCols / $parts);
        $chunks = [];

        for ($p = 0; $p < $parts; $p++) {
            $start = 1 + $p * $per;
            $len = min($per, $dataCols - $p * $per);
            $idx = array_merge([0], range($start, $start + $len - 1));

            if ($p === $parts - 1 && $trailing) {
                $idx = array_merge($idx, range($dataCols + 1, $dataCols + $trailing));
            }

            $pick = fn(array $a) => array_map(fn($i) => $a[$i], $idx);
            $chunk = $table;
            $chunk['headers'] = $pick($table['headers']);
            $chunk['formats'] = $pick($table['formats']);
            $chunk['rows'] = array_map(fn($r) => ['cells' => $pick($r['cells']), 'total' => $r['total']], $table['rows']);
            $chunk['part'] = $p + 1;
            $chunk['parts'] = $parts;
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * 1 234,56 (money), 1 234 (int), 12,5% (pct), +12,5% (change). Null = "–".
     */
    public static function format($value, string $format): string
    {
        if ($format === 'text') {
            return (string)$value;
        }

        if ($value === null) {
            return '–';
        }

        $value = (float)$value;

        switch ($format) {
            case 'int':
                return number_format($value, 0, ',', self::NBSP);
            case 'pct':
                return number_format($value, 1, ',', self::NBSP) . '%';
            case 'change':
                return ($value > 0.0004 ? '+' : '') . number_format($value, 1, ',', self::NBSP) . '%';
            case 'money':
            default:
                return number_format($value, 2, ',', self::NBSP);
        }
    }

    /**
     * Company logo for PDF / print: embedded as a data URI when it is a local file (dompdf does not need to fetch it),
     * otherwise its URL. Null when the company has no own logo.
     */
    public static function logo(?Company $company): ?string
    {
        if (!$company || !$company->logo) {
            return null;
        }

        $path = public_path(Files::UPLOAD_FOLDER . '/app-logo/' . $company->logo);

        if (is_file($path) && filesize($path) < 2 * 1024 * 1024) {
            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'jpg', 'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                'gif' => 'image/gif',
                default => 'image/png',
            };

            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        }

        return $company->logo_url;
    }

    // Excel

    public function filename(): string
    {
        return self::baseName($this->config) . '.xlsx';
    }

    public function sheets(): array
    {
        $data = self::prepare($this->config, $this->filters, $this->companyId);
        $sheets = [];

        foreach ($data['tables'] as $table) {
            $sheets[] = $this->sheet($data, $table);
        }

        return $sheets;
    }

    protected function sheet(array $data, array $table)
    {
        $meta = [
            $data['title'],
            trim($data['company_name'] . '   ' . __('databackd-reports-export.generated_at') . ': ' . $data['generated_at']),
            implode('  |  ', array_merge($data['summary'], $data['config_lines'])),
        ];

        $sheetTitle = $table['id'] === 'main' ? __('databackd-reports-export.sheet.main') : __('databackd-reports-export.sheet.' . $table['id']);

        return new class($meta, $table, $sheetTitle) implements FromArray, WithTitle, WithColumnWidths, WithEvents, WithStrictNullComparison {

            use NeutralizesFormulas;

            const HEADER_ROW = 4;

            public function __construct(protected array $meta, protected array $table, protected string $sheetTitle)
            {
            }

            public function title(): string
            {
                return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $this->sheetTitle), 0, 31);
            }

            public function array(): array
            {
                // S6/P4: every text cell here can carry user-controlled data (company name, dimension/counterparty/
                // account/legal-entity names used as row labels or - when the report groups by one of those - as
                // the column headers themselves), so all three meta lines and both the header row and the "text"
                // formatted row cells are run through neutralizeRow()/neutralizeFormula() against CSV/Excel formula
                // injection. Numeric/percentage cells are never strings, so they pass through unaffected.
                $out = [
                    [self::neutralizeFormula($this->meta[0])],
                    [self::neutralizeFormula($this->meta[1])],
                    [self::neutralizeFormula($this->meta[2])],
                    self::neutralizeRow($this->table['headers']),
                ];
                $formats = $this->table['formats'];

                foreach ($this->table['rows'] as $row) {
                    $cells = [];

                    foreach ($row['cells'] as $i => $v) {
                        $f = $formats[$i];
                        // percentages are stored as fractions with a % cell format
                        $cells[] = $v === null ? null : ($f === 'text' ? self::neutralizeFormula((string)$v) : ((float)$v / (in_array($f, ['pct', 'change'], true) ? 100 : 1)));
                    }

                    $out[] = $cells;
                }

                return $out;
            }

            public function columnWidths(): array
            {
                $count = count($this->table['headers']);
                $many = $count > 20;
                $widths = ['A' => 42];

                for ($i = 2; $i <= $count; $i++) {
                    $widths[Coordinate::stringFromColumnIndex($i)] = $many ? 13 : 16;
                }

                return $widths;
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet->getDelegate();
                        $cols = count($this->table['headers']);
                        $last = Coordinate::stringFromColumnIndex($cols);
                        $header = self::HEADER_ROW;
                        $lastRow = $header + count($this->table['rows']);

                        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                        $sheet->getStyle('A2:A3')->getFont()->setItalic(true)->getColor()->setRGB('555555');

                        $sheet->getStyle("A{$header}:{$last}{$header}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3A5F']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        ]);
                        $sheet->getStyle("A{$header}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getRowDimension($header)->setRowHeight(30);

                        if ($lastRow > $header) {
                            // number formats: one style call per run of equal formats
                            $map = [
                                'money' => '#,##0.00', 'int' => '#,##0', 'pct' => '0.0%', 'change' => '+0.0%;-0.0%;0.0%',
                            ];
                            $start = null;
                            $current = null;

                            foreach (array_merge($this->table['formats'], ['end']) as $i => $f) {
                                if ($f !== $current) {
                                    if ($start !== null && isset($map[$current])) {
                                        $a = Coordinate::stringFromColumnIndex($start + 1);
                                        $b = Coordinate::stringFromColumnIndex($i);
                                        $sheet->getStyle($a . ($header + 1) . ':' . $b . $lastRow)->getNumberFormat()->setFormatCode($map[$current]);
                                        $sheet->getStyle($a . ($header + 1) . ':' . $b . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                                    }

                                    $start = $i;
                                    $current = $f;
                                }
                            }

                            $lastData = $header + count($this->table['rows']);
                            $lastRowDef = end($this->table['rows']);

                            if ($lastRowDef && $lastRowDef['total']) {
                                $sheet->getStyle("A{$lastData}:{$last}{$lastData}")->applyFromArray([
                                    'font' => ['bold' => true],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF6']],
                                    'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1F3A5F']]],
                                ]);
                            }

                            // the "Total" column
                            if (($this->table['trailing'] ?? 0) > 0) {
                                $sheet->getStyle("{$last}" . ($header + 1) . ":{$last}{$lastData}")->getFont()->setBold(true);
                            }
                        }

                        $sheet->freezePane('B' . ($header + 1));
                        $sheet->setSelectedCell('A1');
                    },
                ];
            }
        };
    }

}
