<?php

namespace Database\Seeders\Databackd\Demo;

use App\Models\Company;
use App\Models\Databackd\FinSavedReport;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Scopes\CompanyScope;
use App\Services\Databackd\FinancialDataService;
use App\Services\Databackd\Reports\ReportBuilder;
use App\Services\Databackd\Reports\ReportFilters;
use Database\Seeders\Databackd\DatabackdDemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data of the "Reports" package: nine saved reports (pivot builder, P&L, cash flow) with realistic names,
 * descriptions, sharing, schedules, recipients and "last sent" dates. Every pivot configuration is verified to return
 * a non-empty result, otherwise the seeder stops with an exception.
 *
 * Idempotent: all saved reports of the demo company are removed and rebuilt.
 */
class ReportsDemoSeeder extends Seeder
{

    const TODAY = '2026-10-27';

    const ANALYST_EMAIL = 'g.petrov@demofinans.bg';

    protected int $companyId = 0;
    protected ?int $adminId = null;
    protected ?int $analystId = null;

    public function run(Company $company)
    {
        $this->companyId = $company->id;

        $this->adminId = $this->userId(DatabackdDemoSeeder::ADMIN_EMAIL);
        $this->analystId = $this->userId(self::ANALYST_EMAIL) ?: $this->adminId;

        if (!$this->adminId) {
            throw new \RuntimeException('ReportsDemoSeeder needs the S1 demo data (users) - run FinModelDemoSeeder first.');
        }

        $definitions = $this->definitions();
        $this->verify($definitions);

        DB::transaction(function () use ($definitions) {
            FinSavedReport::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->companyId)->delete();

            foreach ($definitions as $d) {
                $report = new FinSavedReport();
                $report->company_id = $this->companyId;
                $report->name = $d['name'];
                $report->description = $d['description'];
                $report->type = $d['type'];
                $report->config = $d['config'];
                $report->is_shared = $d['is_shared'];
                $report->schedule = $d['schedule'];
                $report->recipients = $d['schedule'] === 'none' ? null : $d['recipients'];
                $report->last_sent_at = $d['last_sent_at'];
                $report->created_by = $d['created_by'];
                $report->created_at = $d['created_at'];
                $report->updated_at = $d['updated_at'];
                $report->save();
            }
        });

        if ($this->command) {
            $rows = FinSavedReport::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->companyId)->orderBy('id')->get()
                ->map(fn($r) => [$r->name, $r->type, $r->schedule, $r->is_shared ? 'yes' : 'no', $r->last_sent_at?->format('Y-m-d H:i') ?? '-'])
                ->all();

            $this->command->info('Reports demo: ' . count($rows) . ' saved reports.');
            $this->command->table(['name', 'type', 'schedule', 'is_shared', 'last_sent_at'], $rows);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Definitions
    // ---------------------------------------------------------------------------------------------

    protected function definitions(): array
    {
        $admin = $this->adminId;
        $analyst = $this->analystId;

        $all = ['admin@demofinans.bg', 'g.petrov@demofinans.bg', 'm.dimitrova@demofinans.bg'];

        return [
            [
                'name' => 'Месечни приходи по региони',
                'description' => 'Приходите на групата по региони за последните 12 месеца, разбити по месеци. Използва се за месечния преглед на продажбите.',
                'type' => 'pivot',
                'config' => $this->pivotConfig([
                    'rows' => 'region', 'columns' => 'period', 'account_type' => 'revenue', 'chart' => 'bar',
                ], ['period' => 'last_12_months', 'periodicity' => 'month']),
                'is_shared' => true, 'schedule' => 'monthly', 'recipients' => $all, 'last_sent_at' => '2026-10-01 08:00:00',
                'created_by' => $admin, 'created_at' => '2026-02-12 10:20:00', 'updated_at' => '2026-09-03 14:05:00',
            ],
            [
                'name' => 'Разходи за персонал по тримесечия',
                'description' => 'Външни услуги и възнаграждения по граждански договори (сметки 604 и 605) по тримесечия, с изменение спрямо предходното тримесечие.',
                'type' => 'pivot',
                'config' => $this->pivotConfig([
                    'rows' => 'account', 'columns' => 'period', 'account_codes' => ['604', '605'], 'show_change' => true, 'chart' => 'line',
                ], ['period' => 'custom', 'from' => '2025-05-01', 'to' => '2026-10-31', 'periodicity' => 'quarter']),
                'is_shared' => false, 'schedule' => 'none', 'recipients' => [], 'last_sent_at' => null,
                'created_by' => $analyst, 'created_at' => '2026-03-04 11:45:00', 'updated_at' => '2026-09-15 16:30:00',
            ],
            [
                'name' => 'Топ клиенти 2026',
                'description' => 'Десетте най-големи контрагента по приходи от началото на годината, по тримесечия, с дял от общия оборот. Изпраща се всяка седмица на търговското ръководство.',
                'type' => 'pivot',
                'config' => $this->pivotConfig([
                    'rows' => 'counterparty', 'columns' => 'period', 'account_type' => 'revenue', 'limit' => 10,
                    'percent_of_total' => true, 'chart' => 'bar',
                ], ['period' => 'this_year', 'periodicity' => 'quarter']),
                'is_shared' => true, 'schedule' => 'weekly', 'recipients' => ['admin@demofinans.bg', 'm.dimitrova@demofinans.bg'], 'last_sent_at' => '2026-10-23 08:00:00',
                'created_by' => $admin, 'created_at' => '2026-04-20 09:10:00', 'updated_at' => '2026-10-12 13:40:00',
            ],
            [
                'name' => 'Дневни постъпления — октомври 2026',
                'description' => 'Дневни приходи по сметки за текущия месец. Изпраща се всяка сутрин за оперативно наблюдение на постъпленията.',
                'type' => 'pivot',
                'config' => $this->pivotConfig([
                    'rows' => 'account', 'columns' => 'period', 'account_type' => 'revenue', 'chart' => 'line',
                ], ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31', 'periodicity' => 'day']),
                'is_shared' => false, 'schedule' => 'daily', 'recipients' => ['g.petrov@demofinans.bg', 'm.dimitrova@demofinans.bg'], 'last_sent_at' => '2026-10-27 07:00:00',
                'created_by' => $analyst, 'created_at' => '2026-10-01 08:30:00', 'updated_at' => '2026-10-19 09:15:00',
            ],
            [
                'name' => 'Маржове по продуктови групи',
                'description' => 'Приходи по продуктови групи за последните 12 месеца, по тримесечия, с дял на всяка група от общите приходи.',
                'type' => 'pivot',
                'config' => $this->pivotConfig([
                    'rows' => 'product_group', 'columns' => 'period', 'account_type' => 'revenue', 'percent_of_total' => true, 'chart' => 'pie',
                ], ['period' => 'last_12_months', 'periodicity' => 'quarter']),
                'is_shared' => true, 'schedule' => 'none', 'recipients' => [], 'last_sent_at' => null,
                'created_by' => $analyst, 'created_at' => '2026-05-07 15:00:00', 'updated_at' => '2026-08-21 10:25:00',
            ],
            [
                'name' => 'Годишно сравнение 2025/2026',
                'description' => 'Приходи и разходи по години от началото на данните до октомври 2026 – с процентно изменение за ключовите показатели.',
                'type' => 'pivot',
                'config' => $this->pivotConfig([
                    'rows' => 'account_type', 'columns' => 'period', 'show_change' => true, 'chart' => 'bar',
                ], ['period' => 'custom', 'from' => '2025-01-01', 'to' => '2026-10-31', 'periodicity' => 'year']),
                'is_shared' => true, 'schedule' => 'none', 'recipients' => [], 'last_sent_at' => null,
                'created_by' => $admin, 'created_at' => '2026-06-16 12:00:00', 'updated_at' => '2026-10-05 11:10:00',
            ],
            [
                'name' => 'ОПР — Q3 2026',
                'description' => 'Отчет за приходите и разходите за третото тримесечие на 2026 г., по месеци.',
                'type' => 'pnl',
                'config' => ['filters' => ReportFilters::fromArray([
                    'period' => 'custom', 'from' => '2026-07-01', 'to' => '2026-09-30', 'periodicity' => 'month',
                ])->toQuery()],
                'is_shared' => true, 'schedule' => 'none', 'recipients' => [], 'last_sent_at' => null,
                'created_by' => $admin, 'created_at' => '2026-10-06 09:45:00', 'updated_at' => '2026-10-06 09:45:00',
            ],
            [
                'name' => 'Паричен поток — 12 месеца',
                'description' => 'Паричният поток на групата за последните 12 месеца. Изпраща се в началото на всеки месец на управителите.',
                'type' => 'cashflow',
                'config' => ['filters' => ReportFilters::fromArray([
                    'period' => 'last_12_months', 'periodicity' => 'month',
                ])->toQuery()],
                'is_shared' => true, 'schedule' => 'monthly', 'recipients' => ['admin@demofinans.bg', 'g.petrov@demofinans.bg'], 'last_sent_at' => '2026-10-01 08:00:00',
                'created_by' => $admin, 'created_at' => '2026-01-22 10:00:00', 'updated_at' => '2026-07-14 17:20:00',
            ],
            [
                'name' => 'Разходи по центрове на разходи',
                'description' => 'Разходите по центрове на разходи за последните 6 месеца, по месеци – за контрол на бюджета на отделите.',
                'type' => 'pivot',
                'config' => $this->pivotConfig([
                    'rows' => 'cost_center', 'columns' => 'period', 'account_type' => 'expense', 'percent_of_total' => true, 'chart' => 'bar',
                ], ['period' => 'last_6_months', 'periodicity' => 'month']),
                'is_shared' => false, 'schedule' => 'weekly', 'recipients' => ['admin@demofinans.bg'], 'last_sent_at' => '2026-10-26 08:00:00',
                'created_by' => $analyst, 'created_at' => '2026-08-11 13:30:00', 'updated_at' => '2026-10-20 15:45:00',
            ],
        ];
    }

    /**
     * Config of a pivot report: ReportBuilder config + chart + filters (ReportFilters::toQuery()).
     */
    protected function pivotConfig(array $builder, array $filters): array
    {
        $config = ReportBuilder::normalizeConfig($builder);
        $config['filters'] = ReportFilters::fromArray($filters)->toQuery();

        return $config;
    }

    // ---------------------------------------------------------------------------------------------
    // Verification
    // ---------------------------------------------------------------------------------------------

    /**
     * Every report must give data: pivots need at least one row with a non-zero total, P&L / cash flow need revenue or expense.
     */
    protected function verify(array $definitions): void
    {
        $data = new FinancialDataService($this->companyId);
        $builder = new ReportBuilder($data);

        foreach ($definitions as $d) {
            $filters = ReportFilters::fromArray($d['config']['filters'] ?? []);

            if ($d['type'] === 'pivot') {
                $pivot = $builder->pivot($d['config'], $filters);
                $nonEmpty = collect($pivot['rows'])->contains(fn($row) => abs((float)$row['total']) > 0.0);

                if (!$nonEmpty) {
                    throw new \RuntimeException('ReportsDemoSeeder: report "' . $d['name'] . '" returns no data - check its configuration.');
                }

                continue;
            }

            $sums = $data->sumByAccountType($filters->from, $filters->to, $filters->toServiceFilters());

            if (empty($sums['revenue']) && empty($sums['expense'])) {
                throw new \RuntimeException('ReportsDemoSeeder: report "' . $d['name'] . '" returns no data - check its period.');
            }
        }
    }

    protected function userId(string $email): ?int
    {
        return User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])
            ->where('company_id', $this->companyId)->where('email', $email)->value('id');
    }

}
