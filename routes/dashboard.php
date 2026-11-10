<?php

use Illuminate\Support\Facades\Route;
use N2ns\Stats\Http\Controllers\DashboardController;
use N2ns\Stats\Http\Controllers\SiteController;

// Mounted with config('stats.dashboard_middleware') under config('stats.dashboard_path'); every route requires the viewStats gate.
Route::middleware('can:viewStats')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('stats.dashboard');
    Route::get('sites/{publicId}', [DashboardController::class, 'show'])->name('stats.dashboard.site');
    Route::get('sites/{publicId}/export/{report}', [DashboardController::class, 'export'])->name('stats.dashboard.export');
    Route::get('manage/sites', [SiteController::class, 'index'])->name('stats.sites.index');
    Route::post('manage/sites', [SiteController::class, 'store'])->name('stats.sites.store');
    Route::put('manage/sites/{publicId}', [SiteController::class, 'update'])->name('stats.sites.update');
    Route::post('manage/sites/{publicId}/key', [SiteController::class, 'rotateKey'])->name('stats.sites.key');
});
