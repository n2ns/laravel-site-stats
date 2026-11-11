<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use N2ns\Stats\Tests\TestCase;

// A host that shows the statistics in its own admin (e.g. the Filament plugin) turns the standalone dashboard off.
beforeAll(fn () => TestCase::$config = ['stats.dashboard_enabled' => false]);
afterAll(fn () => TestCase::$config = []);

it('registers no standalone dashboard or site management routes when disabled', function () {
    Gate::define('viewStats', fn ($user = null) => true);

    expect(Route::has('stats.dashboard'))->toBeFalse()
        ->and(Route::has('stats.sites.index'))->toBeFalse()
        ->and(Route::has('stats.dashboard.export'))->toBeFalse();
    $this->get('/stats')->assertNotFound();
});

it('keeps the script, collector and read API', function () {
    expect(Route::has('stats.script'))->toBeTrue()
        ->and(Route::has('stats.collect'))->toBeTrue()
        ->and(Route::has('stats.api.sites'))->toBeTrue();
    $this->get('/js/stats.js')->assertOk();
});
