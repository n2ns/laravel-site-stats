<?php

use Illuminate\Support\Facades\Route;
use N2ns\Stats\Http\Controllers\Api\ReportController;
use N2ns\Stats\Http\Controllers\CollectController;
use N2ns\Stats\Http\Controllers\ScriptController;
use N2ns\Stats\Http\Controllers\ServerEventController;

// Mounted on config('stats.collector_domain') (or the current domain under collector_path), no session/CSRF/cookies.
Route::get('js/stats.js', ScriptController::class)->name('stats.script');
Route::post('api/event', CollectController::class)->name('stats.collect');
Route::post('api/server-event', ServerEventController::class)->name('stats.server-event');

Route::middleware('throttle:stats-api')->prefix('api/v1')->group(function (): void {
    Route::get('sites', [ReportController::class, 'sites'])->name('stats.api.sites');
    Route::get('sites/{publicId}/summary', [ReportController::class, 'summary'])->name('stats.api.summary');
    Route::get('sites/{publicId}/timeseries', [ReportController::class, 'timeseries'])->name('stats.api.timeseries');
    Route::get('sites/{publicId}/breakdown/{report}', [ReportController::class, 'breakdown'])->name('stats.api.breakdown');
});
