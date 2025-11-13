<?php

namespace App\Console\Commands\Databackd;

use App\Mail\Databackd\SavedReportMail;
use App\Models\Company;
use App\Models\Databackd\FinSavedReport;
use App\Scopes\CompanyScope;
use App\Services\Databackd\Reports\ReportFilters;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * databackd:send-reports - generates a PDF of the saved reports that are due and e-mails it to the recipients.
 *
 * "Due" (calendar based, so a cron run at a fixed hour never drifts):
 *   daily   - not sent today
 *   weekly  - last sent 7 or more days ago (by date)
 *   monthly - not sent in the current calendar month
 *   (never sent reports are due on the first run; "none" is never due unless --force)
 *
 * Schedule (routes/console.php or Kernel::schedule): Schedule::command('databackd:send-reports')->dailyAt('07:00');
 */
class SendSavedReports extends Command
{

    /** report type => PDF class (all expose a static render()) */
    const PDF_CLASSES = [
        'pivot' => 'App\\Exports\\Databackd\\Reports\\PivotPdf',
        'pnl' => 'App\\Exports\\Databackd\\Reports\\PnlPdf',
        'cashflow' => 'App\\Exports\\Databackd\\Reports\\CashflowPdf',
        'counterparties' => 'App\\Exports\\Databackd\\Reports\\CounterpartyPdf',
    ];

    protected $signature = 'databackd:send-reports
                            {--dry-run : Only show what would be sent; nothing is sent and last_sent_at is not touched}
                            {--company= : Only the reports of this company id}
                            {--report= : Only this saved report id}
                            {--force : Send every report with a schedule and recipients, even if it is not due yet}';

    protected $description = 'E-mail the saved financial reports that are due (daily / weekly / monthly) as PDF';

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $force = (bool)$this->option('force');
        $originalLocale = App::getLocale();

        try {
            $query = FinSavedReport::withoutGlobalScope(CompanyScope::class)
                ->where('schedule', '!=', 'none')
                ->orderBy('company_id')
                ->orderBy('id');

            if ($this->option('company')) {
                $query->where('company_id', (int)$this->option('company'));
            }

            if ($this->option('report')) {
                $query->where('id', (int)$this->option('report'));
            }

            $reports = $query->get()->filter(fn(FinSavedReport $report) => $force || self::isDue($report));

            if ($reports->isEmpty()) {
                $this->info($this->t('no_reports'));

                return self::SUCCESS;
            }

            $this->info($this->t('found', ['count' => $reports->count()]));
            $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

            foreach ($reports->groupBy('company_id') as $companyId => $companyReports) {
                $company = Company::find($companyId);

                foreach ($companyReports as $report) {
                    $replace = ['name' => $report->name, 'id' => $report->id];

                    if (!$company || ($company->status ?? 'active') !== 'active' || !databackd_company_module((int)$companyId, 'databackd_reports')) { // AFV (S8): the package must include the reports module
                        $this->warn($this->t('skipped_company', $replace));
                        $stats['skipped']++;
                        continue;
                    }

                    $recipients = array_values(array_filter((array)$report->recipients, fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));

                    if (!$recipients) {
                        $this->warn($this->t('skipped_recipients', $replace));
                        $stats['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line($this->t('dry_run', $replace + ['type' => $report->type, 'schedule' => $report->schedule, 'recipients' => implode(', ', $recipients)]));
                        continue;
                    }

                    App::setLocale($company->locale ?: config('app.locale'));

                    try {
                        $class = self::PDF_CLASSES[$report->type] ?? null;

                        if (!$class || !class_exists($class)) {
                            $this->warn($this->t('skipped_class', $replace + ['class' => $class ?: $report->type]));
                            $stats['skipped']++;
                            continue;
                        }

                        $pdf = $this->renderPdf($report, $class, (int)$company->id);
                        $filename = $this->filename($report);

                        Mail::to($recipients)->send(new SavedReportMail($report, $pdf, $filename, $company->company_name));

                        $report->forceFill(['last_sent_at' => now()])->save();
                        $this->info($this->t('sent', $replace + ['count' => count($recipients)]));
                        $stats['sent']++;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        $this->error($this->t('failed', $replace + ['error' => $e->getMessage()]));
                        Log::error('databackd:send-reports failed for report #' . $report->id . ': ' . $e->getMessage(), ['exception' => $e]);
                    } finally {
                        App::setLocale($originalLocale);
                    }
                }
            }

            if (!$dryRun) {
                $this->line($this->t('summary', $stats));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            Log::error('databackd:send-reports: ' . $e->getMessage(), ['exception' => $e]);

            return self::FAILURE;
        } finally {
            App::setLocale($originalLocale);
        }
    }

    /**
     * Is the report due for sending now? Public static so the rule can be reused / tested.
     */
    public static function isDue(FinSavedReport $report, ?Carbon $now = null): bool
    {
        $now = ($now ?: now())->copy();
        $last = $report->last_sent_at;

        if ($report->schedule === 'none' || !in_array($report->schedule, FinSavedReport::SCHEDULES, true)) {
            return false;
        }

        if (!$last) {
            return true;
        }

        $today = $now->copy()->startOfDay();

        return match ($report->schedule) {
            'daily' => $last->copy()->startOfDay()->lt($today),
            'weekly' => $last->copy()->startOfDay()->lte($today->copy()->subDays(7)),
            'monthly' => $last->format('Y-m') !== $now->format('Y-m'),
            default => false,
        };
    }

    protected function renderPdf(FinSavedReport $report, string $class, int $companyId): string
    {
        $config = (array)$report->config;
        // the company id of the saved report: no logged-in user here, so the names in the PDF title are looked up in this company only
        $filters = ReportFilters::fromArray((array)($config['filters'] ?? []), $companyId);

        if ($report->type === 'pivot') {
            return $class::render($config, $filters, $companyId);
        }

        if ($report->type === 'counterparties') {
            return $class::render($filters, $companyId, (string)($config['side'] ?? 'revenue'));
        }

        return $class::render($filters, $companyId);
    }

    protected function filename(FinSavedReport $report): string
    {
        $slug = Str::slug(Str::transliterate($report->name)) ?: __('databackd-reports-saved.mail.attachment_name');

        return Str::limit($slug, 60, '') . '-' . now()->format('Y-m-d') . '.pdf';
    }

    protected function t(string $key, array $replace = []): string
    {
        return __('databackd-reports-saved.command.' . $key, $replace);
    }

}
