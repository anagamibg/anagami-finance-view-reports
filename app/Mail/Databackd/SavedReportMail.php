<?php

namespace App\Mail\Databackd;

use App\Models\Company;
use App\Models\Databackd\FinSavedReport;
use App\Services\Databackd\Reports\ReportFilters;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Route;

/**
 * Scheduled saved report: short description, the report parameters, a link and the PDF as an attachment.
 * Works without a logged in user (used by `databackd:send-reports`).
 */
class SavedReportMail extends Mailable
{

    public string $companyName;
    /** @var string[] */
    public array $summary = [];
    public ?string $openUrl = null;
    /** "d.m.Y H:i" in the timezone of the company (the app runs in UTC) */
    public string $generatedAt = '';

    public function __construct(public FinSavedReport $report, public string $pdf, public string $filename, ?string $companyName = null)
    {
        $company = Company::find($report->company_id);
        $this->companyName = $companyName ?? (string)$company?->company_name;
        $this->generatedAt = now(ReportFilters::companyTimezone((int)$report->company_id))->format('d.m.Y H:i');

        $config = (array)$report->config;
        $this->summary = ReportFilters::fromArray((array)($config['filters'] ?? []), (int)$report->company_id)->summary();

        if (Route::has('databackd.reports.saved.open') && config('app.url')) {
            $this->openUrl = route('databackd.reports.saved.open', $report->id);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('databackd-reports-saved.mail.subject', ['name' => $this->report->name, 'company' => $this->companyName])
        );
    }

    public function content(): Content
    {
        return new Content(view: 'databackd.reports.saved.mail');
    }

    /** @return Attachment[] */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn() => $this->pdf, $this->filename)->withMime('application/pdf'),
        ];
    }

}
