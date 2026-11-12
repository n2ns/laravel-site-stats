<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Stats;

beforeEach(function () {
    $this->site = Site::create(['name' => 'Shop', 'domains' => ['shop.example']]);
    Site::create(['name' => 'Blog', 'domains' => ['blog.example']]);
});

it('finds the site of the app by host, or the pinned one', function () {
    expect(Site::forHost('www.shop.example')?->name)->toBe('Shop')
        ->and(Site::forHost('other.example'))->toBeNull();

    config(['stats.host_site' => $this->site->public_id]);
    expect(Site::forHost('blog.example')?->name)->toBe('Shop');
});

it('prints the script tag for the site of the current request', function () {
    Route::get('/page', fn () => Blade::render("<head>@statsScript</head>|<head>@statsScript(['data-pageviews' => 'off', 'onload' => 'x', 'data-exclude-paths' => '/a\"b'])</head>"));

    $html = $this->get('https://shop.example/page')->assertOk()->getContent();
    [$plain, $withAttributes] = explode('|', $html);

    expect($plain)->toBe('<head><script defer data-site="'.$this->site->public_id.'" src="https://shop.example/js/stats.js"></script></head>')
        ->and($withAttributes)->toContain('data-pageviews="off"')
        ->toContain('data-exclude-paths="/a&quot;b"')
        ->not->toContain('onload');
    $this->get('https://unknown.example/page')->assertSee('<head></head>', false);
});

it('points the script tag to the host in client mode', function () {
    config(['stats.mode' => 'client', 'stats.client.site' => '01JCZ0000000000000000000AB', 'stats.client.endpoint' => 'https://stats.example.com/']);

    expect((string) Stats::script())->toBe('<script defer data-site="01JCZ0000000000000000000AB" src="https://stats.example.com/js/stats.js"></script>');

    config(['stats.client.endpoint' => null]);
    expect((string) Stats::script())->toBe('');
});

it('shows no site switcher when there is a single site', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    Site::where('name', 'Blog')->delete();

    $this->get(route('stats.dashboard.site', ['publicId' => $this->site->public_id]))->assertOk()
        ->assertDontSee('<select name="site"', false)
        ->assertSee('<span class="stats-site-name">Shop</span>', false);

    Site::create(['name' => 'Blog', 'domains' => ['blog.example']]);
    $this->get(route('stats.dashboard.site', ['publicId' => $this->site->public_id]))->assertSee('<select name="site"', false);
});

it('creates the app\'s own site when installing with --site', function () {
    $this->artisan('stats:install', ['--force' => true, '--site' => 'App', '--domain' => ['App.example', '*.app.example']])
        ->expectsOutputToContain('Site created: App')
        ->expectsOutputToContain('@statsScript')
        ->assertSuccessful();
    expect(Site::where('name', 'App')->sole()->domains)->toBe(['app.example', '*.app.example']);

    $this->artisan('stats:install', ['--force' => true, '--site' => 'Again', '--domain' => ['app.example']])
        ->expectsOutputToContain('already counts app.example')
        ->assertSuccessful();
    expect(Site::where('name', 'Again')->exists())->toBeFalse();

    $this->artisan('stats:install', ['--force' => true, '--site' => 'Bad', '--domain' => ['127.0.0.1']])->assertFailed();
    $this->artisan('stats:install', ['--force' => true, '--site' => 'NoDomain'])->assertFailed();
})->after(fn () => File::delete(config_path('stats.php')));

it('prints no script tag instead of failing the page when the site lookup fails', function () {
    Route::get('/page', fn () => Blade::render('<head>@statsScript</head>'));
    DB::beforeExecuting(function (string $query): void {
        if (str_contains($query, 'stats_sites')) {
            throw new RuntimeException('Forced lookup failure.');
        }
    });

    $this->get('https://shop.example/page')->assertOk()->assertSee('<head></head>', false);
});

it('prints no script tag when the pinned site does not own the request host', function () {
    config(['stats.host_site' => $this->site->public_id]);
    Route::get('/page', fn () => Blade::render('@statsScript'));

    $this->get('https://evil.example/page')->assertOk()->assertDontSee('<script', false);
    $this->get('https://shop.example/page')->assertSee('src="https://shop.example/js/stats.js"', false);
});

it('finds the oldest matching site when domains overlap', function () {
    Site::create(['name' => 'Overlap', 'domains' => ['*.shop.example']]);

    expect(Site::forHost('shop.example')?->name)->toBe('Shop');
});

it('does not create a site whose domain another site already counts, checking every domain', function () {
    $this->artisan('stats:install', ['--force' => true, '--site' => 'New', '--domain' => ['new.example', 'blog.example']])
        ->expectsOutputToContain('already counts blog.example')
        ->assertFailed();
    expect(Site::where('name', 'New')->exists())->toBeFalse();
})->after(fn () => File::delete(config_path('stats.php')));

it('rejects --domain without --site, site options in client mode and too many domains', function () {
    $this->artisan('stats:install', ['--force' => true, '--domain' => ['x.example']])->assertFailed();

    $domains = array_map(fn ($i) => "d{$i}.example", range(1, Site::MAX_DOMAINS + 1));
    $this->artisan('stats:install', ['--force' => true, '--site' => 'Many', '--domain' => $domains])->assertFailed();

    config(['stats.mode' => 'client']);
    $this->artisan('stats:install', ['--site' => 'C', '--domain' => ['c.example']])->assertFailed();
    expect(Site::whereIn('name', ['Many', 'C'])->exists())->toBeFalse();
})->after(fn () => File::delete(config_path('stats.php')));

it('gives the site created while installing the UTC timezone, like stats:site create', function () {
    config(['app.timezone' => 'Asia/Shanghai']);
    $this->artisan('stats:install', ['--force' => true, '--site' => 'Tz', '--domain' => ['tz.example']])->assertSuccessful();

    expect(Site::where('name', 'Tz')->value('timezone'))->toBe('UTC');
})->after(fn () => File::delete(config_path('stats.php')));

it('stops when the migrations did not run', function () {
    Artisan::command('migrate {--force}', fn () => 1);

    $this->artisan('stats:install', ['--site' => 'M', '--domain' => ['m.example']])->assertFailed();
    expect(Site::where('name', 'M')->exists())->toBeFalse();
})->after(fn () => File::delete(config_path('stats.php')));
