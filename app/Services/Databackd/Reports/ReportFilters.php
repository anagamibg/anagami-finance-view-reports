<?php

namespace App\Services\Databackd\Reports;

use App\Models\Company;
use App\Models\Databackd\FinDimension;
use App\Models\Databackd\FinEntity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Common filter bar state of all report screens (dashboard, builder, P&L, cash flow, counterparties).
 *
 * Query string parameters (see toQuery()):
 *   period            today|this_week|this_month|last_3_months|last_6_months|last_12_months|this_year|last_year|custom
 *   from, to          Y-m-d, used when period=custom
 *   periodicity       day|week|month|quarter|year
 *   entity_ids[]      fin_entities.id
 *   region_id, client_segment_id, product_group_id, cost_center_id   fin_dimensions.id
 *   client_id         users.id of the client
 *   compare           none|previous|last_year
 *
 * Default: last 12 months, monthly, no comparison.
 */
class ReportFilters
{

    const PERIODS = ['today', 'this_week', 'this_month', 'last_3_months', 'last_6_months', 'last_12_months', 'this_year', 'last_year', 'custom'];
    const COMPARE = ['none', 'previous', 'last_year'];
    const DIMENSION_KEYS = ['region_id', 'client_segment_id', 'product_group_id', 'cost_center_id'];

    public string $period = 'last_12_months';
    public Carbon $from;
    public Carbon $to;
    public string $periodicity = 'month';
    /** @var int[] */
    public array $entityIds = [];
    public ?int $regionId = null;
    public ?int $clientSegmentId = null;
    public ?int $productGroupId = null;
    public ?int $costCenterId = null;
    public ?int $clientId = null;
    public string $compare = 'none';

    /**
     * Tenant for the names shown by summary() (S6/P1). Set explicitly where no logged-in user exists (databackd:send-reports,
     * mail); otherwise summary() uses the company of the logged-in user. The ids in the filters come from the query string /
     * a saved report config, so a name is only looked up inside this company.
     */
    public ?int $companyId = null;

    public function __construct()
    {
        [$this->from, $this->to] = self::resolvePeriod($this->period);
    }

    public static function fromRequest(Request $r): self
    {
        return self::fromArray($r->all());
    }

    /**
     * Builds the filters from a query-string like array (also used to reopen saved reports).
     *
     * @param int|null $companyId tenant of the names shown by summary() - pass it when there is no logged-in user (console, mail)
     */
    public static function fromArray(array $q, ?int $companyId = null): self
    {
        $f = new self();
        $f->companyId = $companyId ?: null;
        // S12: quick periods are relative to "today" of the company (the app runs in UTC, a company in Europe/Sofia
        // would see yesterday as "today" until 02:00/03:00 local time)
        $timezone = self::companyTimezone($f->companyId);
        [$f->from, $f->to] = self::resolvePeriod($f->period, $timezone);

        $period = (string)($q['period'] ?? '');
        $from = self::parseDate($q['from'] ?? null);
        $to = self::parseDate($q['to'] ?? null);

        if (in_array($period, self::PERIODS, true) && $period !== 'custom') {
            $f->period = $period;
            [$f->from, $f->to] = self::resolvePeriod($period, $timezone);
        } elseif ($from && $to) {
            $f->period = 'custom';
            $f->from = Carbon::parse($from->toDateString(), $timezone)->startOfDay();
            $f->to = Carbon::parse($to->toDateString(), $timezone)->endOfDay();

            if ($f->to->lt($f->from)) {
                [$f->from, $f->to] = [$f->to->copy()->startOfDay(), $f->from->copy()->endOfDay()];
            }
        }

        if (in_array($q['periodicity'] ?? '', PeriodBuckets::PERIODICITIES, true)) {
            $f->periodicity = $q['periodicity'];
        }

        $entities = $q['entity_ids'] ?? ($q['entities'] ?? []);
        $f->entityIds = array_values(array_unique(array_filter(array_map('intval', (array)$entities))));

        $f->regionId = self::intOrNull($q['region_id'] ?? null);
        $f->clientSegmentId = self::intOrNull($q['client_segment_id'] ?? null);
        $f->productGroupId = self::intOrNull($q['product_group_id'] ?? null);
        $f->costCenterId = self::intOrNull($q['cost_center_id'] ?? null);
        $f->clientId = self::intOrNull($q['client_id'] ?? null);

        if (in_array($q['compare'] ?? '', self::COMPARE, true)) {
            $f->compare = $q['compare'];
        }

        return $f;
    }

    /**
     * Compact array for URLs and for saved report configs. A quick period is stored as such (so a saved
     * "last 12 months" report stays relative); only "custom" stores the dates.
     */
    public function toQuery(): array
    {
        $q = ['period' => $this->period];

        if ($this->period === 'custom') {
            $q['from'] = $this->from->toDateString();
            $q['to'] = $this->to->toDateString();
        }

        $q['periodicity'] = $this->periodicity;

        if ($this->entityIds) {
            $q['entity_ids'] = $this->entityIds;
        }

        foreach ([
            'region_id' => $this->regionId, 'client_segment_id' => $this->clientSegmentId,
            'product_group_id' => $this->productGroupId, 'cost_center_id' => $this->costCenterId,
            'client_id' => $this->clientId,
        ] as $key => $value) {
            if ($value) {
                $q[$key] = $value;
            }
        }

        if ($this->compare !== 'none') {
            $q['compare'] = $this->compare;
        }

        return $q;
    }

    /**
     * Filters in the format of FinancialDataService (entity_ids, region_id, ... client_id).
     */
    public function toServiceFilters(): array
    {
        $filters = [];

        if ($this->entityIds) {
            $filters['entity_ids'] = $this->entityIds;
        }

        foreach ([
            'region_id' => $this->regionId, 'client_segment_id' => $this->clientSegmentId,
            'product_group_id' => $this->productGroupId, 'cost_center_id' => $this->costCenterId,
            'client_id' => $this->clientId,
        ] as $key => $value) {
            if ($value) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * Comparison period: "previous" = the period of the same length right before, "last_year" = the same dates
     * one year earlier. Null when compare = none.
     *
     * @return array{from: Carbon, to: Carbon}|null
     */
    public function comparePeriod(): ?array
    {
        if ($this->compare === 'last_year') {
            // S12: no overflow - 29.02.2024 -> 28.02.2023 (not 01.03.2023); a range ending on the last day of a month
            // ends on the last day of the same month a year earlier
            $to = $this->to->copy()->subYearNoOverflow();

            if ($this->to->isSameDay($this->to->copy()->endOfMonth())) {
                $to = $to->endOfMonth();
            }

            return ['from' => $this->from->copy()->subYearNoOverflow()->startOfDay(), 'to' => $to->endOfDay()];
        }

        if ($this->compare === 'previous') {
            // S12: whole calendar months (this month, last 3/6/12 months, a quarter, a custom 01.03-31.03) compare with
            // the same number of whole months before - March with February, not with 29.01-28.02
            if ($this->from->day === 1 && $this->to->isSameDay($this->to->copy()->endOfMonth())) {
                $months = ($this->to->year - $this->from->year) * 12 + $this->to->month - $this->from->month + 1;
                $from = $this->from->copy()->startOfMonth()->subMonthsNoOverflow($months);

                return ['from' => $from->startOfDay(), 'to' => $this->from->copy()->subDay()->endOfDay()];
            }

            $days = $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
            $to = $this->from->copy()->subDay()->endOfDay();

            return ['from' => $to->copy()->subDays($days - 1)->startOfDay(), 'to' => $to];
        }

        return null;
    }

    /** Same filters with another date range (used for the comparison period). */
    public function withRange(Carbon $from, Carbon $to): self
    {
        $copy = clone $this;
        $copy->period = 'custom';
        $copy->from = $from->copy()->startOfDay();
        $copy->to = $to->copy()->endOfDay();
        $copy->compare = 'none';

        return $copy;
    }

    /** @return array<int, array{key: string, label: string, from: Carbon, to: Carbon}> */
    public function buckets(): array
    {
        return PeriodBuckets::make($this->from, $this->to, $this->periodicity);
    }

    /**
     * Human readable lines for report titles (PDF, print, e-mails): "Период: ...", "Юридическо лице: ...".
     *
     * @return string[]
     */
    public function summary(): array
    {
        $lines = [__('databackd-reports-common.filters.period') . ': ' . $this->from->format('d.m.Y') . ' – ' . $this->to->format('d.m.Y')];
        $lines[] = __('databackd-reports-common.filters.periodicity') . ': ' . __('databackd-reports-common.periodicities.' . $this->periodicity);

        // S6/P1: ids come from the URL / a saved report - names are looked up inside ONE company only (explicit company_id,
        // because the global CompanyScope is not active in console and User is read without scopes)
        $companyId = $this->companyId ?: (company() ? (int)company()->id : null);
        $idLabel = fn(array $ids) => '#' . implode(', #', $ids);

        if ($this->entityIds) {
            $names = $companyId
                ? FinEntity::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $this->entityIds)->orderBy('sort')->pluck('name')->implode(', ')
                : '';
            $lines[] = __('databackd-reports-common.filters.entities') . ': ' . ($names !== '' ? $names : $idLabel($this->entityIds));
        }

        foreach ([
            'region' => $this->regionId, 'client_segment' => $this->clientSegmentId,
            'product_group' => $this->productGroupId, 'cost_center' => $this->costCenterId,
        ] as $type => $id) {
            if ($id) {
                $name = $companyId ? FinDimension::withoutGlobalScopes()->where('company_id', $companyId)->where('id', $id)->value('name') : null;
                $lines[] = __('databackd-reports-common.dimensions.' . $type) . ': ' . ($name ?? '#' . $id);
            }
        }

        if ($this->clientId) {
            $name = $companyId ? User::withoutGlobalScopes()->where('company_id', $companyId)->where('id', $this->clientId)->value('name') : null;
            $lines[] = __('databackd-reports-common.filters.client') . ': ' . ($name ?? '#' . $this->clientId);
        }

        if ($this->compare !== 'none') {
            $lines[] = __('databackd-reports-common.filters.compare') . ': ' . __('databackd-reports-common.compare.' . $this->compare);
        }

        return $lines;
    }

    /**
     * Date range of a quick period, relative to today. Month based periods end with the current month.
     *
     * @return array{0: Carbon, 1: Carbon} [from (00:00), to (23:59:59)]
     */
    public static function resolvePeriod(string $period, ?string $timezone = null): array
    {
        $now = Carbon::now($timezone ?: self::companyTimezone(null));

        switch ($period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'this_week':
                return [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)];
            case 'this_month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'last_3_months':
                return [$now->copy()->subMonthsNoOverflow(2)->startOfMonth(), $now->copy()->endOfMonth()];
            case 'last_6_months':
                return [$now->copy()->subMonthsNoOverflow(5)->startOfMonth(), $now->copy()->endOfMonth()];
            case 'this_year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfMonth()];
            case 'last_year':
                $y = $now->copy()->subYear();

                return [$y->copy()->startOfYear(), $y->copy()->endOfYear()];
            case 'last_12_months':
            default:
                return [$now->copy()->subMonthsNoOverflow(11)->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }

    /** Timezone of the company (explicit id, else the logged-in company), else the app timezone. */
    public static function companyTimezone(?int $companyId): string
    {
        try {
            $company = $companyId ? Company::find($companyId) : company();
            $timezone = $company ? (string)$company->timezone : '';

            if ($timezone !== '' && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                return $timezone;
            }
        } catch (\Throwable $e) {
            // no company (console without --company, tests) - fall back to the app timezone
        }

        return (string)config('app.timezone', 'UTC');
    }

    /**
     * Strict Y-m-d: an impossible date (31.02, month 13) is rejected instead of rolling over into the next month/year.
     */
    protected static function parseDate($value): ?Carbon
    {
        if (!$value || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', substr((string)$value, 0, 10), $m)) {
            return null;
        }

        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || (int)$m[1] < 1900 || (int)$m[1] > 2200) {
            return null;
        }

        return Carbon::create((int)$m[1], (int)$m[2], (int)$m[3], 0, 0, 0);
    }

    protected static function intOrNull($value): ?int
    {
        return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
    }

}
