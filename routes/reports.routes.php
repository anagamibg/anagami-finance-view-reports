<?php

/*
 * REFERENCE ONLY - not loaded by the application.
 *
 * Extract of the route definitions of the base (upstream) reports from routes/web.php (lines 875-917 of the
 * Anagami Finance View source). In the original they sit inside:
 *
 *   Route::group(['middleware' => ['auth', 'multi-company-select', 'email_verified'], 'prefix' => 'account'], function () { ... });
 *
 * routes/web-settings.php, routes/web-public.php and routes/api.php contain no report routes.
 * The AFV report builder / saved reports / export routes are kept as-is in routes/databackd/reports-{builder,saved,export}.php.
 */

use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\ExpenseReportController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\IncomeVsExpenseReportController;
use App\Http\Controllers\LeadReportController;
use App\Http\Controllers\LeavesQuotaController;
use App\Http\Controllers\LeaveReportController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\TaskReportController;
use App\Http\Controllers\TimelogReportController;
use App\Http\Controllers\TimelogWeeklyApprovalController;
use Illuminate\Support\Facades\Route;

    Route::post('task-report-chart', [TaskReportController::class, 'taskChartData'])->name('task-report.chart');
    Route::get('task-report/employee-wise-task-report', [TaskReportController::class, 'employeeWiseTaskReport'])->name('employee-wise-task-report');
    Route::get('task-report/consolidated-task-report', [TaskReportController::class, 'consolidatedTaskReport'])->name('consolidated-task-report');

    Route::resource('task-report', TaskReportController::class);

    Route::post('time-log-report-chart', [TimelogReportController::class, 'timelogChartData'])->name('time-log-report.chart');
    Route::get('time-log-consolidated-report', [TimelogReportController::class, 'consolidateIndex'])->name('time-log-consolidated.report');
    Route::get('time-log-project-wise-report', [TimelogReportController::class, 'projectWiseTimelog'])->name('project-wise-timelog.report');
    Route::get('project-wise-timelog/report/export', [TimelogReportController::class, 'exportProjectWiseTimeLog'])->name('project-wise-timelog.export');
    Route::resource('time-log-report', TimelogReportController::class);
    Route::post('time-log-report-time', [TimelogReportController::class, 'totalTime'])->name('time-log-report.time');

    Route::resource('time-log-weekly-report', TimelogWeeklyApprovalController::class);
    Route::get('weekly-pending-time-log-report', [TimelogWeeklyApprovalController::class, 'pendingTimelogReportIndex'])->name('weekly-pending-time-log-report.report');

    Route::post('finance-report-chart', [FinanceReportController::class, 'financeChartData'])->name('finance-report.chart');
    Route::resource('finance-report', FinanceReportController::class);

    Route::resource('income-expense-report', IncomeVsExpenseReportController::class);

    Route::get('leave-report/leave-quota', [LeaveReportController::class, 'leaveQuota'])->name('leave-report.leave_quota');
    Route::get('leave-report/leave-quota/export-all-leave-quota/{id}/{year}/{month}', [LeavesQuotaController::class, 'exportAllLeaveQuota'])->name('leave_quota.export_all_leave_quota');
    Route::get('leave-report/leave-quota/{id}/{year}/{month}', [LeaveReportController::class, 'employeeLeaveQuota'])->name('leave-report.employee-leave-quota');
    Route::resource('leave-report', LeaveReportController::class);

    Route::resource('attendance-report', AttendanceReportController::class);

    Route::post('expense-report-chart', [ExpenseReportController::class, 'expenseChartData'])->name('expense-report.chart');
    Route::get('expense-report/expense-category-report', [ExpenseReportController::class, 'expenseCategoryReport'])->name('expense-report.expense_category_report');

    Route::resource('expense-report', ExpenseReportController::class);
    Route::get('deal-report/lead', [LeadReportController::class, 'leadContact'])->name('lead-report.lead_contact');
    Route::get('lead-report/total', [LeadReportController::class, 'totalContact'])->name('lead-report.total_contact');

    Route::get('deal-report/chart', [LeadReportController::class, 'averageDealSizeReport'])->name('lead-report.chart');
    Route::get('deal-report/profile', [LeadReportController::class, 'profile'])->name('lead-report.profile');
    Route::get('deal-report/export/{year}/{pipeline}/{category}', [LeadReportController::class, 'exportDealReport'])->name('deal-report.export');



    Route::resource('lead-report', LeadReportController::class);
    Route::resource('sales-report', SalesReportController::class);
