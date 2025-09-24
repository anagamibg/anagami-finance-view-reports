<?php

use App\Http\Controllers\Databackd\Reports\ReportExportController;
use Illuminate\Support\Facades\Route;

// Loaded by RouteServiceProvider::mapDatabackdRoutes() with the "account" prefix and the web/auth middleware.
Route::prefix('databackd/reports/export')->middleware(\App\Http\Middleware\Databackd\RequireModule::class . ':databackd_reports')->name('databackd.reports.export.')->group(function () {
    Route::get('excel', [ReportExportController::class, 'excel'])->name('excel');
    Route::get('pdf', [ReportExportController::class, 'pdf'])->name('pdf');
    Route::get('print', [ReportExportController::class, 'print'])->name('print');
});
