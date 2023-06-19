<?php

namespace App\Http\Controllers;

use App\DataTables\AttendanceReportDataTable;
use App\Models\User;

class AttendanceReportController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.attendanceReport';
        // AFV (audit 2026-10-30, G08): the module check its sibling controllers have (no package bypass by URL)
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('reports', user_modules()) || !in_array('attendance', user_modules()));

            return $next($request);
        });
    }

    public function index(AttendanceReportDataTable $dataTable)
    {
        abort_403(user()->permission('view_attendance_report') != 'all');

        if (!request()->ajax()) {
            $this->fromDate = now($this->company->timezone)->startOfMonth();
            $this->toDate = now($this->company->timezone);
            $this->employees = User::allEmployees();
        }

        return $dataTable->render('reports.attendance.index', $this->data);
    }

}
