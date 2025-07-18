<?php

use App\Http\Controllers\Databackd\Reports\SavedReportController;
use Illuminate\Support\Facades\Route;

// Loaded by RouteServiceProvider::mapDatabackdRoutes() with the "account" prefix and the web/auth middleware.
Route::prefix('databackd/reports/saved')->middleware(\App\Http\Middleware\Databackd\RequireModule::class . ':databackd_reports')->name('databackd.reports.saved.')->group(function () {
    Route::get('/', [SavedReportController::class, 'index'])->name('index');
    Route::post('/', [SavedReportController::class, 'store'])->name('store');
    Route::get('{id}', [SavedReportController::class, 'open'])->whereNumber('id')->name('open');
    Route::post('{id}/duplicate', [SavedReportController::class, 'duplicate'])->whereNumber('id')->name('duplicate');
    Route::delete('{id}', [SavedReportController::class, 'destroy'])->whereNumber('id')->name('destroy');
});
