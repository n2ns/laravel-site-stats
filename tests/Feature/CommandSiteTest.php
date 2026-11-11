<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\Tables;

it('creates a site and prints the embed snippet', function () {
    expect(Artisan::call('stats:site', ['action' => 'create', '--name' => 'Example', '--domain' => ['Example.com', '*.example.org'], '--timezone' => 'Europe/Berlin']))->toBe(0);

    $site = Site::sole();
    expect(Artisan::output())->toContain('<script defer data-site="'.$site->public_id.'" src="http://localhost/js/stats.js"></script>');
    expect($site->name)->toBe('Example')
        ->and($site->domains)->toBe(['example.com', '*.example.org'])
        ->and($site->timezone)->toBe('Europe/Berlin')
        ->and($site->public_id)->toHaveLength(26);
});

it('defaults the timezone to UTC', function () {
    $this->artisan('stats:site', ['action' => 'create', '--name' => 'A', '--domain' => ['a.com']])->assertSuccessful();

    expect(Site::sole()->timezone)->toBe('UTC');
});

it('rejects invalid input on create', function (array $options) {
    $this->artisan('stats:site', ['action' => 'create', ...$options])->assertFailed();

    expect(Site::count())->toBe(0);
})->with([
    'no name' => [['--domain' => ['a.com']]],
    'no domain' => [['--name' => 'A']],
    'url as domain' => [['--name' => 'A', '--domain' => ['https://a.com']]],
    'ip as domain' => [['--name' => 'A', '--domain' => ['127.0.0.1']]],
    'single label' => [['--name' => 'A', '--domain' => ['localhost']]],
    'wildcard in the middle' => [['--name' => 'A', '--domain' => ['a.*.com']]],
    'bad timezone' => [['--name' => 'A', '--domain' => ['a.com'], '--timezone' => 'Mars/Base']],
]);

it('lists sites in a table', function () {
    Site::create(['name' => 'Alpha', 'domains' => ['alpha.com', 'www.alpha.com']]);

    expect(Artisan::call('stats:site', ['action' => 'list']))->toBe(0);

    expect(Artisan::output())->toContain('Alpha')->toContain('alpha.com, www.alpha.com')->toContain('UTC');
});

it('updates name, domains and timezone', function () {
    $site = Site::create(['name' => 'Old', 'domains' => ['old.com']]);

    $this->artisan('stats:site', ['action' => 'update', '--site' => $site->public_id, '--name' => 'New', '--domain' => ['new.com'], '--timezone' => 'Asia/Tokyo'])
        ->assertSuccessful();

    $site->refresh();
    expect($site->name)->toBe('New')->and($site->domains)->toBe(['new.com'])->and($site->timezone)->toBe('Asia/Tokyo');
});

it('keeps unspecified fields on update and requires a change', function () {
    $site = Site::create(['name' => 'Old', 'domains' => ['old.com']]);

    $this->artisan('stats:site', ['action' => 'update', '--site' => $site->public_id, '--timezone' => 'Asia/Tokyo'])->assertSuccessful();
    $this->artisan('stats:site', ['action' => 'update', '--site' => $site->public_id])->assertFailed();
    $this->artisan('stats:site', ['action' => 'update', '--site' => 'missing', '--name' => 'X'])->assertFailed();

    expect($site->refresh()->domains)->toBe(['old.com'])->and($site->name)->toBe('Old');
});

function seedSiteRows(Site $site): void
{
    $base = ['site_id' => $site->id, 'date' => '2026-11-11'];
    DB::table(Tables::name('daily_totals'))->insert($base + ['pageviews' => 3]);
    DB::table(Tables::name('daily_pages'))->insert($base + ['path' => '/', 'path_hash' => 'h']);
    DB::table(Tables::name('daily_sources'))->insert($base + ['source_key' => 'k', 'source_type' => 'direct']);
    DB::table(Tables::name('daily_dimensions'))->insert($base + ['dimension' => 'country', 'value' => 'DE']);
    DB::table(Tables::name('daily_events'))->insert($base + ['kind' => 'custom', 'name' => 'x', 'label_hash' => 'l', 'target_hash' => 't', 'path_hash' => 'p']);
    DB::table(Tables::name('daily_entries'))->insert($base + ['path' => '/', 'path_hash' => 'h', 'source_key' => 'k', 'source_type' => 'direct']);
    DB::table(Tables::name('daily_transitions'))->insert($base + ['from_path' => '/', 'from_hash' => 'h', 'to_path' => '/b', 'to_hash' => 'b']);
    DB::table(Tables::name('uniques'))->insert($base + ['scope' => 'site', 'visitor_hash' => 'v']);
    DB::table(Tables::name('visits'))->insert($base + [
        'visitor_hash' => 'v', 'started_at' => '2026-11-11 10:00:00', 'last_seen_at' => '2026-11-11 10:00:00',
        'entry_path_hash' => 'h', 'exit_path_hash' => 'h', 'source_key' => 'k', 'dimensions' => '{}',
    ]);
}

it('deletes a site and all of its rows after confirmation', function () {
    $site = Site::create(['name' => 'Gone', 'domains' => ['gone.com']]);
    $other = Site::create(['name' => 'Kept', 'domains' => ['kept.com']]);
    seedSiteRows($site);
    seedSiteRows($other);

    $this->artisan('stats:site', ['action' => 'delete', '--site' => $site->public_id])
        ->expectsConfirmation('Delete site "Gone" ('.$site->public_id.') and all of its statistics?', 'yes')
        ->assertSuccessful();

    expect(Site::pluck('name')->all())->toBe(['Kept']);
    foreach (['uniques', 'visits', 'daily_totals', 'daily_pages', 'daily_sources', 'daily_dimensions', 'daily_events', 'daily_entries', 'daily_transitions'] as $table) {
        expect(DB::table(Tables::name($table))->where('site_id', $site->id)->count())->toBe(0)
            ->and(DB::table(Tables::name($table))->where('site_id', $other->id)->count())->toBe(1);
    }
});

it('keeps the site when the deletion is not confirmed', function () {
    $site = Site::create(['name' => 'Stay', 'domains' => ['stay.com']]);

    $this->artisan('stats:site', ['action' => 'delete', '--site' => $site->public_id])
        ->expectsConfirmation('Delete site "Stay" ('.$site->public_id.') and all of its statistics?', 'no')
        ->assertFailed();

    expect(Site::count())->toBe(1);
});

it('deletes without asking with --force', function () {
    $site = Site::create(['name' => 'Gone', 'domains' => ['gone.com']]);

    $this->artisan('stats:site', ['action' => 'delete', '--site' => $site->public_id, '--force' => true])->assertSuccessful();

    expect(Site::count())->toBe(0);
});

it('generates a server key, stores only its hash and prints the client env lines', function () {
    $site = Site::create(['name' => 'Client', 'domains' => ['client.com']]);

    expect(Artisan::call('stats:site', ['action' => 'key', '--site' => $site->public_id]))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('STATS_MODE=client')
        ->toContain('STATS_CLIENT_ENDPOINT=http://localhost'.PHP_EOL)
        ->toContain('STATS_CLIENT_SITE='.$site->public_id);

    preg_match('/STATS_CLIENT_KEY=(\S+)/', $output, $m);
    $site->refresh();
    expect($m[1] ?? null)->toHaveLength(40)
        ->and($site->server_key_hash)->toBe(hash('sha256', $m[1]))
        ->and($site->verifiesServerKey($m[1]))->toBeTrue();
});

it('fails for an unknown action or site', function () {
    $this->artisan('stats:site', ['action' => 'rename'])->assertFailed();
    $this->artisan('stats:site', ['action' => 'key', '--site' => 'nope'])->assertFailed();
    $this->artisan('stats:site', ['action' => 'key'])->assertFailed();
});

it('sets locales, excluded paths and public counts on create and lists them', function () {
    $this->artisan('stats:site', ['action' => 'create', '--name' => 'A', '--domain' => ['a.com'], '--locale' => ['EN', 'zh-CN', 'en'],
        '--default-locale' => 'En', '--exclude-path' => ['/admin*', '/preview/*'], '--public-counts' => true])->assertSuccessful();

    $site = Site::sole();
    expect($site->locales)->toBe(['en', 'zh-cn'])
        ->and($site->default_locale)->toBe('en')
        ->and($site->exclude_paths)->toBe(['/admin*', '/preview/*'])
        ->and($site->public_counts)->toBeTrue();

    expect(Artisan::call('stats:site', ['action' => 'list']))->toBe(0);
    expect(Artisan::output())->toContain('en, zh-cn')->toContain('/admin*, /preview/*')->toContain('Public counts')->toContain('yes');
});

it('creates sites without locales and with public counts off by default', function () {
    $this->artisan('stats:site', ['action' => 'create', '--name' => 'A', '--domain' => ['a.com']])->assertSuccessful();

    $site = Site::sole();
    expect($site->locales)->toBeNull()->and($site->exclude_paths)->toBeNull()->and($site->public_counts)->toBeFalse();
});

it('updates locales, excluded paths and public counts', function () {
    $site = Site::create(['name' => 'A', 'domains' => ['a.com'], 'locales' => ['en'], 'public_counts' => true]);

    $this->artisan('stats:site', ['action' => 'update', '--site' => $site->public_id, '--no-public-counts' => true])->assertSuccessful();
    expect($site->refresh()->public_counts)->toBeFalse()->and($site->locales)->toBe(['en']);

    $this->artisan('stats:site', ['action' => 'update', '--site' => $site->public_id, '--locale' => ['de', 'pt-br'], '--default-locale' => 'de', '--exclude-path' => ['/x']])
        ->assertSuccessful();
    $site->refresh();
    expect($site->locales)->toBe(['de', 'pt-br'])->and($site->default_locale)->toBe('de')->and($site->exclude_paths)->toBe(['/x'])
        ->and($site->name)->toBe('A');

    $this->artisan('stats:site', ['action' => 'update', '--site' => $site->public_id, '--public-counts' => true])->assertSuccessful();
    expect($site->refresh()->public_counts)->toBeTrue();
});

it('rejects invalid locales, excluded paths and public counts options', function (array $options, string $message) {
    $site = Site::create(['name' => 'A', 'domains' => ['a.com']]);

    $this->artisan('stats:site', ['action' => 'update', '--site' => $site->public_id, ...$options])
        ->expectsOutputToContain($message)
        ->assertFailed();
    $this->artisan('stats:site', ['action' => 'create', '--name' => 'B', '--domain' => ['b.com'], ...$options])->assertFailed();

    expect(Site::count())->toBe(1)->and($site->refresh()->locales)->toBeNull()->and($site->exclude_paths)->toBeNull();
})->with([
    'locale with underscore' => [['--locale' => ['zh_CN']], 'Invalid --locale "zh_cn"'],
    'locale too long' => [['--locale' => ['english']], 'Invalid --locale'],
    'locale region too long' => [['--locale' => ['en-abcdefghi']], 'Invalid --locale'],
    'too many locales' => [['--locale' => array_map(fn ($i) => 'l'.chr(97 + $i), range(0, 20))], 'At most 20 --locale'],
    'bad default locale' => [['--default-locale' => 'en/us'], 'Invalid --default-locale'],
    'path without slash' => [['--exclude-path' => ['admin']], 'Invalid --exclude-path "admin"'],
    'path too long' => [['--exclude-path' => ['/'.str_repeat('a', 200)]], 'at most 200 characters'],
    'too many paths' => [['--exclude-path' => array_map(fn ($i) => '/p'.$i, range(1, 51))], 'At most 50 --exclude-path'],
    'both public flags' => [['--public-counts' => true, '--no-public-counts' => true], 'not both'],
]);
