<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(config_path('stats.php'));
});

it('publishes the config, migrates and prints the next steps', function () {
    File::delete(config_path('stats.php'));

    $this->artisan('stats:install', ['--force' => true])
        ->expectsOutputToContain("Gate::define('viewStats'")
        ->expectsOutputToContain('stats:site create')
        ->expectsOutputToContain('schedule:run')
        ->expectsOutputToContain('TrackAiFetches')
        ->expectsOutputToContain('StatsPlugin')
        ->assertSuccessful();

    expect(File::exists(config_path('stats.php')))->toBeTrue();
});

it('skips the migrations in client mode', function () {
    config(['stats.mode' => 'client']);

    $this->artisan('stats:install')
        ->expectsOutputToContain('Client mode')
        ->expectsOutputToContain('stats:site key')
        ->doesntExpectOutputToContain('viewStats')
        ->assertSuccessful();
});
