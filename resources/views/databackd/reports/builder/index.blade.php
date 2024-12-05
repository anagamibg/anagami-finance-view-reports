@extends('layouts.app')

@section('content')
    @php
        $action = route('databackd.reports.builder.index');
        $filterOptions = ['period', 'periodicity', 'entities', 'dimensions', 'client', 'compare'];
        // Same id as the one computed by the _filters partial: the builder fields below belong to its form
        $formId = 'rf' . substr(md5($action . serialize($filterOptions)), 0, 6) . '-form';

        $measure = $config['measure'];
        $fmt = fn($v) => $measure === 'count' ? number_format((float)$v, 0, ',', ' ') : number_format((float)$v, 2, ',', ' ');
        $unit = __('databackd-reports-builder.units.' . $measure);
        $pct = fn($v) => number_format((float)$v, 1, ',', ' ') . '%';
        $dimName = fn($d) => __('databackd-reports-common.dimensions.' . $d);

        // Heat colour of the cells by the type of the report: revenue green, expenses red, mixed blue (rgb in _reports.scss)
        $heat = ['revenue' => 'revenue', 'expense' => 'expense', 'all' => 'all'][$config['account_type']];
        // Expenses growing is bad news
        $goodUp = $config['account_type'] !== 'expense';
        $changeClass = function ($v) use ($goodUp) {
            if ($v === null) {
                return 'rb-flat dbk-text-muted';
            }

            if (abs($v) < 0.05) {
                return 'rb-flat dbk-text-muted';
            }

            return ($v > 0) === $goodUp ? 'rb-up dbk-text-good' : 'rb-down dbk-text-bad';
        };
        $arrow = fn($v) => $v === null ? '' : (abs($v) < 0.05 ? '–' : ($v > 0 ? '▲' : '▼'));

        $queryRoute = fn($name) => \Route::has($name) ? route($name, $exportQuery) : null;
        $excelUrl = $queryRoute('databackd.reports.export.excel');
        $pdfUrl = $queryRoute('databackd.reports.export.pdf');
        $printUrl = $queryRoute('databackd.reports.export.print');
        $savedUrl = \Route::has('databackd.reports.saved.index') ? route('databackd.reports.saved.index') : null;

        $showPct = $config['percent_of_total'];
        $showChange = $config['show_change'] && $config['columns'] === 'period';
        $columnsLabel = $config['columns'] === 'period'
            ? $dimName('period') . ' (' . mb_strtolower(__('databackd-reports-common.periodicities.' . $filters->periodicity)) . ')'
            : $dimName($config['columns']);
    @endphp

    <div class="content-wrapper dbk-reports dbk-reports-builder">

        <x-dbk.page-header :title="__('databackd-reports-builder.title')" :subtitle="__('databackd-reports-builder.subtitle')">
            <x-slot name="actions">
                <div class="dbk-reports-actions rb-no-print">
                    @includeIf('databackd.reports.saved._save-button', ['type' => 'pivot', 'config' => $saveConfig])

                    @if ($savedUrl)
                        <a href="{{ $savedUrl }}" class="btn btn-secondary btn-sm"><i class="fa fa-bookmark mr-1"></i>@lang('databackd-reports-builder.toolbar.saved')</a>
                    @endif

                    @if ($excelUrl || $pdfUrl || $printUrl)
                        <div class="btn-group" role="group">
                            @if ($excelUrl)
                                <a href="{{ $excelUrl }}" class="btn btn-secondary btn-sm"><i class="fa fa-file-excel mr-1"></i>@lang('databackd-reports-builder.toolbar.excel')</a>
                            @endif
                            @if ($pdfUrl)
                                <a href="{{ $pdfUrl }}" class="btn btn-secondary btn-sm"><i class="fa fa-file-pdf mr-1"></i>@lang('databackd-reports-builder.toolbar.pdf')</a>
                            @endif
                            @if ($printUrl)
                                <a href="{{ $printUrl }}" target="_blank" class="btn btn-secondary btn-sm"><i class="fa fa-print mr-1"></i>@lang('databackd-reports-builder.toolbar.print')</a>
                            @endif
                        </div>
                    @endif
                </div>
            </x-slot>
        </x-dbk.page-header>

        {{-- Common filter bar (its form also carries the builder fields below through the form attribute). S12: no own
             Apply/Clear here - "Update report" below submits the same form (one primary button per screen). --}}
        @include('databackd.reports._filters', ['filters' => $filters, 'options' => $filterOptions, 'action' => $action, 'showActions' => false])

        {{-- BUILDER --}}
        <x-dbk.card class="mb-3 rb-no-print dbk-reports-constructor" :title="__('databackd-reports-builder.constructor')">
            <div class="dbk-reports-fields">
                <div class="dbk-filter">
                    <label class="rb-label" for="rb-rows">@lang('databackd-reports-builder.rows')</label>
                    <select name="rows" id="rb-rows" form="{{ $formId }}" class="form-control select-picker" data-size="9">
                        @foreach ($rowDimensions as $dim)
                            <option value="{{ $dim }}" @selected($config['rows'] === $dim)>{{ $dimName($dim) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="dbk-filter">
                    <label class="rb-label" for="rb-columns">@lang('databackd-reports-builder.columns')</label>
                    <select name="columns" id="rb-columns" form="{{ $formId }}" class="form-control select-picker" data-size="9">
                        <option value="period" @selected($config['columns'] === 'period')>@lang('databackd-reports-builder.columns_by_period')</option>
                        @foreach ($rowDimensions as $dim)
                            <option value="{{ $dim }}" @selected($config['columns'] === $dim)>{{ $dimName($dim) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="dbk-filter">
                    <label class="rb-label" for="rb-measure">@lang('databackd-reports-builder.measure')</label>
                    <select name="measure" id="rb-measure" form="{{ $formId }}" class="form-control select-picker">
                        @foreach (\App\Services\Databackd\Reports\ReportBuilder::MEASURES as $m)
                            <option value="{{ $m }}" @selected($config['measure'] === $m)>@lang('databackd-reports-common.measures.' . $m)</option>
                        @endforeach
                    </select>
                </div>

                <div class="dbk-filter">
                    <label class="rb-label" for="rb-account-type">@lang('databackd-reports-builder.account_type')</label>
                    <select name="account_type" id="rb-account-type" form="{{ $formId }}" class="form-control select-picker">
                        <option value="all" @selected($config['account_type'] === 'all')>@lang('databackd-reports-builder.account_type_all')</option>
                        <option value="revenue" @selected($config['account_type'] === 'revenue')>@lang('databackd-reports-common.account_types.revenue')</option>
                        <option value="expense" @selected($config['account_type'] === 'expense')>@lang('databackd-reports-common.account_types.expense')</option>
                    </select>
                </div>

                <div class="dbk-filter">
                    <label class="rb-label" for="rb-accounts">@lang('databackd-reports-builder.accounts')</label>
                    <select name="account_codes[]" id="rb-accounts" form="{{ $formId }}" class="form-control select-picker" multiple
                        data-size="9" data-actions-box="true" data-live-search="true"
                        data-none-selected-text="@lang('databackd-reports-builder.accounts_all')">
                        @foreach ($accounts->groupBy('type') as $type => $group)
                            <optgroup label="@lang('databackd-reports-common.account_types.' . $type)">
                                @foreach ($group as $account)
                                    <option value="{{ $account->code }}" @selected(in_array((string)$account->code, $config['account_codes'], true))>{{ $account->code }} {{ $account->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div class="dbk-filter">
                    <label class="rb-label" for="rb-limit">@lang('databackd-reports-builder.limit')</label>
                    <select name="limit" id="rb-limit" form="{{ $formId }}" class="form-control select-picker">
                        <option value="0" @selected($config['limit'] === 0)>@lang('databackd-reports-builder.limit_all')</option>
                        @foreach (array_unique(array_merge([5, 10, 15, 20, 30], $config['limit'] ? [$config['limit']] : [])) as $n)
                            <option value="{{ $n }}" @selected($config['limit'] === $n)>@lang('databackd-reports-builder.limit_top', ['n' => $n])</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="dbk-reports-constructor-foot">
                <div class="dbk-reports-checks">
                    <x-forms.checkbox :fieldLabel="__('databackd-reports-builder.percent_of_total')" fieldName="percent_of_total"
                        fieldId="rb-percent" fieldValue="1" :checked="$config['percent_of_total']" form="{{ $formId }}" />
                    <x-forms.checkbox :fieldLabel="__('databackd-reports-builder.show_change')" fieldName="show_change"
                        fieldId="rb-change" fieldValue="1" :checked="$config['show_change']" form="{{ $formId }}" />
                </div>

                <div class="dbk-filter dbk-reports-chart-type">
                    <span class="rb-label">@lang('databackd-reports-builder.chart_type')</span>
                    <input type="hidden" name="chart" id="rb-chart-input" form="{{ $formId }}" value="{{ $config['chart'] }}">
                    <div class="dbk-segmented rb-chart-switch" role="group">
                        @foreach (['bar' => 'chart-bar', 'line' => 'chart-line', 'pie' => 'chart-pie'] as $type => $icon)
                            <button type="button" data-chart="{{ $type }}" class="rb-chart-btn {{ $config['chart'] === $type ? 'active' : '' }}">
                                <i class="fa fa-{{ $icon }} mr-1"></i>@lang('databackd-reports-builder.chart_types.' . $type)
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="dbk-filters-actions">
                    <a href="{{ $resetUrl }}" class="btn btn-secondary btn-sm">@lang('databackd-reports-builder.defaults')</a>
                    <button type="submit" form="{{ $formId }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-sync-alt mr-1"></i>@lang('databackd-reports-builder.apply')
                    </button>
                </div>
            </div>
        </x-dbk.card>

        {{-- RESULT --}}
        @php
            $resultSubtitle = __('databackd-reports-builder.result.subtitle', [
                    'rows' => $dimName($config['rows']),
                    'columns' => mb_strtolower($columnsLabel),
                    'measure' => __('databackd-reports-common.measures.' . $measure) . ($unit ? ' (' . $unit . ')' : ''),
                ])
                . ' · ' . $filters->from->format('d.m.Y') . ' – ' . $filters->to->format('d.m.Y')
                . ' · ' . __('databackd-reports-builder.result.rows_count', ['n' => count($pivot['rows'])]);
        @endphp
        <x-dbk.card class="mb-3" :title="__('databackd-reports-builder.result.title')" :subtitle="$resultSubtitle">
            @foreach ($notices as [$noticeType, $noticeText])
                <div class="alert alert-{{ $noticeType }} rb-notice py-2 mb-2">
                    <i class="fa fa-{{ $noticeType === 'warning' ? 'exclamation-triangle' : 'info-circle' }} mr-1"></i>{{ $noticeText }}
                </div>
            @endforeach

            @if (!$hasData)
                <x-dbk.empty icon="table" :text="__('databackd-reports-builder.notices.empty')" />
            @else
                <div class="dbk-table-wrap dbk-table-wrap--scroll">
                    <table class="dbk-table dbk-table--compact dbk-table--sticky dbk-table--sticky-first rb-table rb-heat-{{ $heat }}" id="rb-table">
                        <thead>
                        <tr>
                            <th class="rb-first" data-type="text" data-col="0">
                                {{ $dimName($config['rows']) }}<span class="rb-sort"></span>
                            </th>
                            @foreach ($tableColumns as $i => $col)
                                <th class="num rb-num" data-type="num" data-col="{{ $i + 1 }}" title="{{ $col['label'] }}">
                                    {{ $col['label'] }}<span class="rb-sort"></span>
                                </th>
                            @endforeach
                            <th class="num rb-num rb-totalcol" data-type="num" data-col="{{ count($tableColumns) + 1 }}">
                                @lang('databackd-reports-builder.result.total_column')<span class="rb-sort"></span>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($tableRows as $row)
                            <tr>
                                <td class="rb-first" data-v="{{ mb_strtolower($row['label']) }}">{{ $row['label'] }}</td>
                                @foreach ($tableColumns as $col)
                                    @php
                                        // Compact markup: wide reports have tens of thousands of cells
                                        $v = (float)($row['values'][$col['key']] ?? 0);
                                        $alpha = $maxCell > 0 ? round(0.04 + 0.3 * min(1, $v / $maxCell), 3) : 0;
                                        $cell = '<td class="num rb-cell' . ($v < 0 ? ' neg' : '') . '" data-v="' . $v . '"' . ($v > 0 ? ' style="--h:' . $alpha . '"' : '') . '>';
                                        $cell .= $v == 0 ? '<span class="rb-zero">–</span>' : e($fmt($v)) . ($showPct ? '<span class="rb-sub">' . e($pct($row['pct'][$col['key']] ?? 0)) . '</span>' : '');
                                        $ch = $showChange ? ($row['change'][$col['key']] ?? null) : null;
                                        $cell .= $ch !== null ? '<span class="rb-chg ' . $changeClass($ch) . '">' . $arrow($ch) . ' ' . e($pct(abs($ch))) . '</span>' : '';
                                    @endphp
{!! $cell !!}</td>
                                @endforeach
                                <td @class(['num rb-totalcol', 'neg' => $row['total'] < 0]) data-v="{{ $row['total'] }}">
                                    {{ $fmt($row['total']) }}
                                    @if ($showPct)
                                        <span class="rb-sub">{{ $pct($row['share'] ?? 0) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        @php $totals = $pivot['totals']; @endphp
                        <tr class="dbk-row-total">
                            <td class="rb-first">{{ $totals['label'] }}</td>
                            @foreach ($tableColumns as $col)
                                @php $tv = (float)($totals['values'][$col['key']] ?? 0); @endphp
                                <td @class(['num rb-cell', 'neg' => $tv < 0])>
                                    {{ $fmt($tv) }}
                                    @if ($showPct)
                                        <span class="rb-sub">{{ $pct($totals['pct'][$col['key']] ?? 0) }}</span>
                                    @endif
                                    @if ($showChange)
                                        @php $ch = $totals['change'][$col['key']] ?? null; @endphp
                                        @if ($ch !== null)
                                            <span class="rb-chg {{ $changeClass($ch) }}">{{ $arrow($ch) }} {{ $pct(abs($ch)) }}</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            <td @class(['num rb-totalcol', 'neg' => $totals['total'] < 0])>
                                {{ $fmt($totals['total']) }}
                                @if ($showPct)
                                    <span class="rb-sub">{{ $pct(100) }}</span>
                                @endif
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="dbk-reports-hint rb-no-print">
                    @lang('databackd-reports-builder.result.sort_hint')
                    @if ($showChange)
                        @lang('databackd-reports-builder.result.change_hint')
                    @endif
                </p>
            @endif
        </x-dbk.card>

        {{-- COMPARISON --}}
        @if ($compare && $hasData)
            @php
                $cmpLabels = collect($pivot['rows'])->merge($compare['pivot']['rows'])->pluck('label', 'key');
                $cmpRows = collect($compare['deltas'])->sortByDesc('current')->all();
                $cmpTotals = $compare['totals'];
                $cmpClass = fn($v) => $changeClass($v);
                $cmpTitle = __('databackd-reports-builder.compare.title', [
                    'current' => $filters->from->format('d.m.Y') . ' – ' . $filters->to->format('d.m.Y'),
                    'previous' => $compare['period']['from']->format('d.m.Y') . ' – ' . $compare['period']['to']->format('d.m.Y'),
                ]);
            @endphp
            <x-dbk.card class="mb-3" :title="$cmpTitle" :subtitle="__('databackd-reports-common.compare.' . $filters->compare)">
                <div class="dbk-table-wrap dbk-table-wrap--scroll">
                    <table class="dbk-table dbk-table--compact dbk-table--sticky dbk-table--sticky-first rb-cmp">
                        <thead>
                        <tr>
                            <th>@lang('databackd-reports-builder.compare.row')</th>
                            <th class="num">@lang('databackd-reports-builder.compare.current')</th>
                            <th class="num">@lang('databackd-reports-builder.compare.previous')</th>
                            <th class="num">@lang('databackd-reports-builder.compare.change')</th>
                            <th class="num">@lang('databackd-reports-builder.compare.change_pct')</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($cmpRows as $key => $d)
                            <tr>
                                <td class="rb-first">{{ $cmpLabels[$key] ?? $key }}</td>
                                <td @class(['num', 'neg' => $d['current'] < 0])>{{ $fmt($d['current']) }}</td>
                                <td @class(['num', 'neg' => $d['previous'] < 0])>{{ $fmt($d['previous']) }}</td>
                                <td class="num {{ $cmpClass($d['change']) }}">{{ $d['change'] > 0 ? '+' : '' }}{{ $fmt($d['change']) }}</td>
                                <td class="num {{ $cmpClass($d['change_pct']) }}">
                                    @if ($d['change_pct'] === null) – @else {{ $arrow($d['change_pct']) }} {{ $pct(abs($d['change_pct'])) }} @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="dbk-row-total">
                            <td class="rb-first">@lang('databackd-reports-common.total')</td>
                            <td @class(['num', 'neg' => $cmpTotals['current'] < 0])>{{ $fmt($cmpTotals['current']) }}</td>
                            <td @class(['num', 'neg' => $cmpTotals['previous'] < 0])>{{ $fmt($cmpTotals['previous']) }}</td>
                            <td @class(['num', 'neg' => $cmpTotals['change'] < 0])>{{ $cmpTotals['change'] > 0 ? '+' : '' }}{{ $fmt($cmpTotals['change']) }}</td>
                            <td class="num">
                                @if ($cmpTotals['change_pct'] === null) – @else {{ $arrow($cmpTotals['change_pct']) }} {{ $pct(abs($cmpTotals['change_pct'])) }} @endif
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </x-dbk.card>
        @endif

        {{-- CHART --}}
        @if ($hasData)
            <x-dbk.card class="mb-4" :title="__('databackd-reports-builder.chart.title')">
                <x-slot name="actions">
                    <div class="dbk-segmented rb-chart-switch rb-no-print" role="group">
                        @foreach (['bar' => 'chart-bar', 'line' => 'chart-line', 'pie' => 'chart-pie'] as $type => $icon)
                            <button type="button" data-chart="{{ $type }}" class="rb-chart-btn {{ $config['chart'] === $type ? 'active' : '' }}">
                                <i class="fa fa-{{ $icon }} mr-1"></i>@lang('databackd-reports-builder.chart_types.' . $type)
                            </button>
                        @endforeach
                    </div>
                </x-slot>
                <p class="dbk-reports-hint mt-0 mb-2" id="rb-chart-note"></p>
                <x-dbk.chart id="rb-chart" size="lg" :label="__('databackd-reports-builder.chart.title')" />
            </x-dbk.card>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // ---- Builder form ----
            // The change vs the previous period makes sense only for period columns
            function syncChange() {
                var isPeriod = $('#rb-columns').val() === 'period';
                $('#rb-change').prop('disabled', !isPeriod);
                if (!isPeriod) {
                    $('#rb-change').prop('checked', false);
                }
            }
            $('#rb-columns').on('change', syncChange);
            syncChange();

            // ---- Sort by a column header (client-side; the totals row stays at the bottom) ----
            var table = document.getElementById('rb-table');
            if (table) {
                var tbody = table.tBodies[0];
                var sortState = {col: null, dir: 1};

                $(table).find('thead th').on('click', function () {
                    var th = $(this);
                    var col = parseInt(th.data('col'), 10);
                    var isNum = th.data('type') === 'num';
                    sortState.dir = sortState.col === col ? -sortState.dir : (isNum ? -1 : 1);
                    sortState.col = col;

                    var rows = Array.prototype.slice.call(tbody.rows);
                    rows.sort(function (a, b) {
                        var x = a.cells[col].getAttribute('data-v');
                        var y = b.cells[col].getAttribute('data-v');
                        var r = isNum ? (parseFloat(x) - parseFloat(y)) : x.localeCompare(y, 'bg');
                        return r * sortState.dir;
                    });
                    rows.forEach(function (r) { tbody.appendChild(r); });

                    $(table).find('.rb-sort').text('');
                    th.find('.rb-sort').text(sortState.dir === 1 ? '▲' : '▼');
                });
            }

            // ---- Chart (dbkChart: categorical colours in a fixed order, the rest folded into "Other") ----
            var data = @json($chartData);
            var seriesLimit = {{ $chartSeriesLimit }};
            var noteTop = @json(__('databackd-reports-builder.chart.top_note', ['n' => '__N__']));
            var notePie = @json(__('databackd-reports-builder.chart.pie_note'));
            var otherLabel = @json(__('databackd-reports-common.other'));
            var canvas = document.getElementById('rb-chart');
            var isCount = data.measure === 'count';
            var canFold = data.measure !== 'avg';
            // At most 8 categories (6 on a phone), ui-guidelines §6 / §11
            var maxSeries = window.innerWidth < 576 ? 6 : 8;

            function money(v) {
                return isCount ? dbkChart.fmt.number(v) + ' ' + data.unit : dbkChart.fmt.money(v);
            }

            function foldSeries(series) {
                if (series.length <= maxSeries) { return {series: series, shown: series.length}; }
                if (!canFold) { return {series: series.slice(0, maxSeries), shown: maxSeries}; }
                var head = series.slice(0, maxSeries - 1);
                var rest = series.slice(maxSeries - 1);
                var other = data.labels.map(function (l, j) {
                    return rest.reduce(function (sum, s) { return sum + (Number(s.data[j]) || 0); }, 0);
                });
                return {series: head.concat([{label: otherLabel, data: other}]), shown: head.length};
            }

            function foldSlices(slices) {
                // The controller already folds the rows after the first seriesLimit into a last "Other" slice
                var extra = slices.length > seriesLimit ? slices[slices.length - 1].value : 0;
                var items = (extra ? slices.slice(0, -1) : slices).map(function (s) { return {label: s.label, value: s.value}; });
                var folded = dbkChart.topN(items, extra ? maxSeries - 1 : maxSeries, otherLabel);
                if (extra) {
                    var last = folded[folded.length - 1];
                    if (last && last.other) { last.value += extra; } else { folded.push({label: otherLabel, value: extra, other: true}); }
                }
                return folded;
            }

            function build(type) {
                if (!canvas) { return; }
                var note = '';
                var axis = {scales: {}};

                if (type === 'pie') {
                    var slices = foldSlices(data.slices);
                    dbkChart.donut(canvas, {
                        labels: slices.map(function (s) { return s.label; }),
                        data: slices.map(function (s) { return s.value; }),
                        unit: money,
                        cutout: '58%',
                        legendPosition: window.innerWidth < 768 ? 'bottom' : 'right'
                    });
                    note = notePie;
                } else {
                    var folded = foldSeries(data.series);
                    var stacked = type === 'bar' && folded.series.length > 1 && data.measure !== 'avg';
                    var datasets = folded.series.map(function (s) { return {label: s.label, data: s.data}; });
                    var opts = {
                        labels: data.labels,
                        datasets: datasets,
                        stacked: stacked,
                        unit: money,
                        legend: datasets.length > 1,
                        options: {scales: {y: {ticks: {callback: function (v) { return dbkChart.fmt.axis(v); }}}}}
                    };
                    if (type === 'line') {
                        opts.beginAtZero = true;
                        dbkChart.line(canvas, opts);
                    } else {
                        dbkChart.bar(canvas, opts);
                    }
                    if (data.truncated || folded.shown < data.series.length) {
                        note = noteTop.replace('__N__', folded.shown);
                    }
                }

                $('#rb-chart-note').text(note).toggle(note !== '');
            }

            // Chart type switch without reloading; the choice is kept in the form (and the URL after "Update")
            $('.rb-chart-btn').on('click', function () {
                var type = $(this).data('chart');
                $('.rb-chart-btn').removeClass('active').filter('[data-chart="' + type + '"]').addClass('active');
                $('#rb-chart-input').val(type);
                build(type);
            });

            build(data.type);
        });
    </script>
@endpush
