{{--
    Common filter bar of the report screens (S3). Plain GET form, state lives in the URL query string.

    @include('databackd.reports._filters', ['filters' => $filters, 'options' => ['period', 'periodicity', 'entities', 'dimensions', 'client', 'compare']])

    $filters  App\Services\Databackd\Reports\ReportFilters
    $options  fields to show; any of: period, periodicity, entities, dimensions, client, compare
    $action   (optional) form action, defaults to the current URL without query string
    $hidden   (optional) array name => value of extra hidden fields to keep (e.g. builder configuration)
    $resetQuery  (optional) query string kept by "Clear" (default: $hidden), e.g. the builder configuration
    $showActions (optional, default true) false = no Apply / Clear here (the screen has its own submit button for this form)

    S12: on a phone (< 768 px) the fields fold behind a "Filters (N)" button - the quick periods and the date range stay
    visible; the submit buttons of the form show a spinner and are disabled while the page loads (no double submit).
--}}
@php
    use App\Models\Databackd\FinDimension;
    use App\Models\Databackd\FinEntity;
    use App\Models\Databackd\FinEntry;
    use App\Services\Databackd\Reports\ReportFilters;

    $options = $options ?? ['period', 'periodicity', 'entities', 'dimensions', 'client', 'compare'];
    $action = $action ?? url()->current();
    $hidden = $hidden ?? [];
    $resetQuery = $resetQuery ?? $hidden;
    $showActions = $showActions ?? true;
    $resetUrl = $action . ($resetQuery ? '?' . http_build_query($resetQuery) : '');
    $uid = 'rf' . substr(md5($action . serialize($options)), 0, 6);

    $has = fn($o) => in_array($o, $options, true);
    $entities = $has('entities') ? FinEntity::orderBy('sort')->orderBy('name')->get(['id', 'name']) : collect();
    $dimensionTypes = $has('dimensions') ? ['region', 'client_segment', 'product_group', 'cost_center'] : [];
    $dimensions = $dimensionTypes ? FinDimension::whereIn('type', $dimensionTypes)->orderBy('name')->get(['id', 'type', 'name'])->groupBy('type') : collect();
    $clients = $has('client')
        ? \App\Models\User::whereIn('id', FinEntry::whereNotNull('client_id')->distinct()->pluck('client_id'))->orderBy('name')->get(['id', 'name'])
        : collect();
    $dimensionValues = ['region' => $filters->regionId, 'client_segment' => $filters->clientSegmentId, 'product_group' => $filters->productGroupId, 'cost_center' => $filters->costCenterId];
    $quickPeriods = array_diff(ReportFilters::PERIODS, ['custom']);
    // active filters besides the period (shown on the folded mobile bar)
    $activeCount = ($has('entities') && $filters->entityIds ? 1 : 0)
        + ($dimensionTypes ? count(array_filter($dimensionValues)) : 0)
        + ($has('client') && $filters->clientId ? 1 : 0)
        + ($has('compare') && $filters->compare !== 'none' ? 1 : 0);
@endphp

<form method="GET" action="{{ $action }}" id="{{ $uid }}-form" class="databackd-report-filters dbk-filters dbk-reports-filters">
    @foreach ($hidden as $name => $value)
        @if (is_array($value))
            @foreach ($value as $v)
                <input type="hidden" name="{{ $name }}[]" value="{{ $v }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    @if ($has('period'))
        <input type="hidden" name="period" id="{{ $uid }}-period" value="{{ $filters->period }}">

        <div class="dbk-reports-periods">
            <div class="dbk-segmented" role="group">
                @foreach ($quickPeriods as $period)
                    <button type="button" data-period="{{ $period }}" aria-pressed="{{ $filters->period === $period ? 'true' : 'false' }}"
                        @class(['is-active' => $filters->period === $period, $uid . '-quick'])>
                        @lang('databackd-reports-common.periods.' . $period)
                    </button>
                @endforeach
            </div>

            <div class="dbk-reports-dates">
                <label for="{{ $uid }}-from">@lang('databackd-reports-common.filters.from')</label>
                <input type="date" class="form-control form-control-sm {{ $uid }}-date" id="{{ $uid }}-from" name="from"
                    value="{{ $filters->from->toDateString() }}">
                <label for="{{ $uid }}-to">@lang('databackd-reports-common.filters.to')</label>
                <input type="date" class="form-control form-control-sm {{ $uid }}-date" id="{{ $uid }}-to" name="to"
                    value="{{ $filters->to->toDateString() }}">
            </div>
        </div>
    @endif

    <div class="dbk-reports-filters-bar">
        <span class="dbk-reports-filters-range">{{ $filters->from->format('d.m.Y') }} – {{ $filters->to->format('d.m.Y') }}</span>
        <button type="button" class="btn btn-secondary btn-sm dbk-reports-filters-toggle" id="{{ $uid }}-toggle" aria-expanded="false" aria-controls="{{ $uid }}-row">
            <i class="fa fa-sliders-h mr-1"></i>@lang('databackd-reports-common.filters.toggle')
            @if ($activeCount)
                <span class="dbk-badge is-info ml-1">{{ $activeCount }}</span><span class="sr-only">@lang('databackd-reports-common.filters.active', ['count' => $activeCount])</span>
            @endif
        </button>
    </div>

    <div class="dbk-filters-row" id="{{ $uid }}-row">
        @if ($has('periodicity'))
            <div class="dbk-filter">
                <label for="{{ $uid }}-periodicity">@lang('databackd-reports-common.filters.periodicity')</label>
                <select name="periodicity" id="{{ $uid }}-periodicity" class="form-control select-picker" data-size="6">
                    @foreach (\App\Services\Databackd\Reports\PeriodBuckets::PERIODICITIES as $p)
                        <option value="{{ $p }}" @selected($filters->periodicity === $p)>@lang('databackd-reports-common.periodicities.' . $p)</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($has('entities'))
            <div class="dbk-filter">
                <label for="{{ $uid }}-entities">@lang('databackd-reports-common.filters.entities')</label>
                <select name="entity_ids[]" id="{{ $uid }}-entities" class="form-control select-picker" multiple data-size="6" data-actions-box="true"
                    data-none-selected-text="@lang('databackd-reports-common.filters.all')">
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}" @selected(in_array($entity->id, $filters->entityIds))>{{ $entity->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @foreach ($dimensionTypes as $type)
            <div class="dbk-filter">
                <label for="{{ $uid }}-{{ $type }}">@lang('databackd-reports-common.dimensions.' . $type)</label>
                <select name="{{ $type }}_id" id="{{ $uid }}-{{ $type }}" class="form-control select-picker" data-size="6">
                    <option value="">@lang('databackd-reports-common.filters.all')</option>
                    @foreach ($dimensions[$type] ?? [] as $dim)
                        <option value="{{ $dim->id }}" @selected($dimensionValues[$type] == $dim->id)>{{ $dim->name }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach

        @if ($has('client'))
            <div class="dbk-filter">
                <label for="{{ $uid }}-client">@lang('databackd-reports-common.filters.client')</label>
                <select name="client_id" id="{{ $uid }}-client" class="form-control select-picker" data-live-search="true" data-size="6">
                    <option value="">@lang('databackd-reports-common.filters.all')</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($filters->clientId == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($has('compare'))
            <div class="dbk-filter">
                <label for="{{ $uid }}-compare">@lang('databackd-reports-common.filters.compare')</label>
                <select name="compare" id="{{ $uid }}-compare" class="form-control select-picker" data-size="6">
                    @foreach (ReportFilters::COMPARE as $c)
                        <option value="{{ $c }}" @selected($filters->compare === $c)>@lang('databackd-reports-common.compare.' . $c)</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($showActions)
            <div class="dbk-filters-actions">
                <a href="{{ $resetUrl }}" class="btn btn-secondary btn-sm">@lang('databackd-reports-common.filters.reset')</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa fa-check mr-1"></i>@lang('databackd-reports-common.filters.apply')
                </button>
            </div>
        @endif
    </div>
</form>

@push('scripts')
    <script>
        (function () {
            var uid = '{{ $uid }}';
            var form = document.getElementById(uid + '-form');
            var $form = $(form);
            // every submit button of this form, also the ones outside it (form="...", e.g. "Update report" of the builder)
            var $submits = $form.find('button[type=submit]').add('button[type=submit][form="' + uid + '-form"]');

            function setBusy(busy) {
                $form.toggleClass('is-loading', busy);
                $submits.prop('disabled', busy).attr('aria-busy', busy ? 'true' : null);
                $submits.find('i.fa').each(function () {
                    var $i = $(this);
                    if (busy) {
                        $i.data('icon', $i.attr('class')).attr('class', 'fa fa-spinner fa-spin mr-1');
                    } else if ($i.data('icon')) {
                        $i.attr('class', $i.data('icon'));
                    }
                });
            }

            // The page reloads with the new query string: block a second submit until it arrives
            $form.on('submit', function () {
                if ($form.hasClass('is-loading')) {
                    return false;
                }
                setBusy(true);
            });

            // Back/forward cache restores the page as it was left - with the spinner
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    setBusy(false);
                }
            });

            // Quick period buttons apply immediately
            $('.' + uid + '-quick').on('click', function () {
                if ($form.hasClass('is-loading')) {
                    return;
                }
                $('#' + uid + '-period').val($(this).data('period'));
                $('.' + uid + '-quick').removeClass('is-active').attr('aria-pressed', 'false');
                $(this).addClass('is-active').attr('aria-pressed', 'true');
                $form.trigger('submit'); // runs the busy state above, then submits
            });

            // Phone: the fields fold behind "Filters (N)" (without JS everything stays open)
            var $toggle = $('#' + uid + '-toggle');
            $form.addClass('is-collapsible is-collapsed');
            $toggle.on('click', function () {
                var open = $form.hasClass('is-collapsed');
                $form.toggleClass('is-collapsed', !open);
                $toggle.attr('aria-expanded', open ? 'true' : 'false');
            });

            // Picking dates switches to the custom period
            $('.' + uid + '-date').on('change', function () {
                $('#' + uid + '-period').val('custom');
            });
        })();
    </script>
@endpush
