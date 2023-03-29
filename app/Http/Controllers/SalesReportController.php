<?php

namespace App\Http\Controllers;

use App\DataTables\SalesReportDataTable;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class SalesReportController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.salesReport';
        // AFV (audit 2026-10-30, G08): the module check its sibling controllers have (no package bypass by URL)
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('reports', user_modules()) || !in_array('invoices', user_modules()));

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(SalesReportDataTable $dataTable) /** @phpstan-ignore-line */
    {
        abort_403(user()->permission('view_sales_report') != 'all');

        if (!request()->ajax()) {
            $this->fromDate = now($this->company->timezone)->startOfMonth();
            $this->toDate = now($this->company->timezone);
        }

        $this->clients = User::allClients();

        return $dataTable->render('reports.sales.index', $this->data); /** @phpstan-ignore-line */
    }

}
