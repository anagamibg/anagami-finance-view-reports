<?php

namespace App\Http\Controllers\Databackd\Reports;

use App\Exports\Databackd\Reports\PivotExport;
use App\Exports\Databackd\Reports\PivotPdf;
use App\Http\Controllers\AccountBaseController;
use App\Services\Databackd\Reports\ReportBuilder;
use App\Services\Databackd\Reports\ReportFilters;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Excel / PDF / print of the report builder. Takes the same query string as the builder page.
 */
class ReportExportController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            // set here: the user's locale is applied by the parent middleware, before this one
            $this->pageTitle = __('databackd-reports-export.title');
            abort_403(in_array('client', user_roles()));

            return $next($request);
        });
    }

    public function excel(Request $request)
    {
        [$config, $filters] = $this->resolve($request);
        $this->allowLongRun();

        $export = new PivotExport($config, $filters, company()->id);

        return Excel::download($export, $export->filename());
    }

    public function pdf(Request $request)
    {
        [$config, $filters] = $this->resolve($request);
        $this->allowLongRun();

        $content = PivotPdf::render($config, $filters, company()->id);
        $name = PivotPdf::filename($config, $filters, company()->id);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length' => strlen($content),
        ]);
    }

    public function print(Request $request)
    {
        [$config, $filters] = $this->resolve($request);

        return view('databackd.reports.export.print', PivotExport::prepare($config, $filters, company()->id));
    }

    /**
     * @return array{0: array, 1: ReportFilters}
     */
    protected function resolve(Request $request): array
    {
        return [ReportBuilder::normalizeConfig($request->query()), ReportFilters::fromRequest($request)];
    }

    protected function allowLongRun(): void
    {
        @set_time_limit(180);
    }

}
