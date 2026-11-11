<?php

use Illuminate\Support\Facades\Artisan;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Tests\TestCase;

// From the CLI there is no request to take the scheme and host from, and APP_URL belongs to the host app.
beforeAll(fn () => TestCase::$config = ['stats.collector_domain' => 'stats.example.com', 'stats.collector_path' => 'c', 'app.url' => 'http://localhost']);
afterAll(fn () => TestCase::$config = []);

it('prints https collector-domain URLs from the CLI', function () {
    expect(Artisan::call('stats:site', ['action' => 'create', '--name' => 'A', '--domain' => ['a.com']]))->toBe(0);
    $site = Site::sole();
    expect(Artisan::output())->toContain('<script defer data-site="'.$site->public_id.'" src="https://stats.example.com/c/js/stats.js"></script>');

    expect(Artisan::call('stats:site', ['action' => 'key', '--site' => $site->public_id]))->toBe(0);
    expect(Artisan::output())->toContain('STATS_CLIENT_ENDPOINT=https://stats.example.com/c'."\n");
});
