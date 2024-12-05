<?php

use App\Http\Controllers\Databackd\Reports\ReportBuilderController;
use Illuminate\Support\Facades\Route;

// Loaded by RouteServiceProvider::mapDatabackdRoutes() with the "account" prefix and the web/auth middleware.
Route::prefix('databackd/reports/builder')->middleware(\App\Http\Middleware\Databackd\RequireModule::class . ':databackd_reports')->name('databackd.reports.builder.')->group(function () {
    Route::get('/', [ReportBuilderController::class, 'index'])->name('index');
});
