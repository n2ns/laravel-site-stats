<?php

use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Tests\StatsSeeder as S;

beforeEach(function () {
    Gate::define('viewStats', fn ($user = null) => true);
});

it('lists sites with their embed snippet', function () {
    $site = S::site('Example', ['domains' => ['example.com', '*.example.org']]);

    $this->get(route('stats.sites.index'))
        ->assertOk()
        ->assertSee('Example')
        ->assertSee('example.com, *.example.org')
        ->assertSee('&lt;script defer data-site="'.$site->public_id.'" src="'.route('stats.script').'"&gt;&lt;/script&gt;', false)
        ->assertSee('Generate server key');
});

it('shows a hint when there are no sites', function () {
    $this->get(route('stats.sites.index'))->assertOk()->assertSee('No sites yet');
});

it('creates a site with normalised domains', function () {
    $this->post(route('stats.sites.store'), [
        'name' => '  My Site ',
        'domains' => "Example.com\r\n\r\n*.example.org\nexample.com\n",
        'timezone' => 'Asia/Ho_Chi_Minh',
    ])->assertRedirect(route('stats.sites.index'))->assertSessionHas('stats_status');

    $site = Site::query()->sole();
    expect($site->name)->toBe('My Site')
        ->and($site->domains)->toBe(['example.com', '*.example.org'])
        ->and($site->timezone)->toBe('Asia/Ho_Chi_Minh')
        ->and($site->public_id)->toHaveLength(26);
});

it('rejects invalid site input', function (array $input, string $field) {
    $this->from(route('stats.sites.index'))
        ->post(route('stats.sites.store'), $input + ['name' => 'X', 'domains' => 'example.com', 'timezone' => 'UTC'])
        ->assertRedirect(route('stats.sites.index'))
        ->assertSessionHasErrorsIn('create', $field);
    expect(Site::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('a', 121)], 'name'],
    'no domains' => [['domains' => "\n \n"], 'domains'],
    'url instead of host' => [['domains' => 'https://example.com'], 'domains'],
    'path' => [['domains' => 'example.com/blog'], 'domains'],
    'ip address' => [['domains' => '192.168.1.1'], 'domains'],
    'bare word' => [['domains' => 'localhost'], 'domains'],
    'bad wildcard' => [['domains' => 'foo.*.example.com'], 'domains'],
    'port' => [['domains' => 'example.com:8080'], 'domains'],
    'too many' => [['domains' => implode("\n", array_map(fn ($i) => "s{$i}.example.com", range(1, 51)))], 'domains'],
    'bad timezone' => [['timezone' => 'Mars/Base'], 'timezone'],
]);

it('shows validation errors next to the right form', function () {
    $this->followingRedirects()
        ->from(route('stats.sites.index'))
        ->post(route('stats.sites.store'), ['name' => 'X', 'domains' => 'not a host', 'timezone' => 'UTC'])
        ->assertOk()
        ->assertSee('is not a hostname', false)
        ->assertSee('not a host');
});

it('updates a site', function () {
    $site = S::site('Old');

    $this->put(route('stats.sites.update', ['publicId' => $site->public_id]), [
        'name' => 'New', 'domains' => "new.example\n*.cdn.example", 'timezone' => 'Europe/Berlin',
    ])->assertRedirect(route('stats.sites.index'));

    $site->refresh();
    expect($site->name)->toBe('New')
        ->and($site->domains)->toBe(['new.example', '*.cdn.example'])
        ->and($site->timezone)->toBe('Europe/Berlin');
});

it('keeps the site unchanged on invalid update', function () {
    $site = S::site('Old');

    $this->from(route('stats.sites.index'))
        ->put(route('stats.sites.update', ['publicId' => $site->public_id]), ['name' => 'New', 'domains' => 'bad host', 'timezone' => 'UTC'])
        ->assertSessionHasErrorsIn('edit_'.$site->public_id, 'domains');
    expect($site->refresh()->name)->toBe('Old');
});

it('returns 404 when updating an unknown site', function () {
    $this->put(route('stats.sites.update', ['publicId' => 'nope']), ['name' => 'X', 'domains' => 'example.com', 'timezone' => 'UTC'])
        ->assertNotFound();
});

it('rotates the server key and shows it only once', function () {
    $site = S::site();

    $response = $this->post(route('stats.sites.key', ['publicId' => $site->public_id]))->assertOk();
    $response->assertHeader('Cache-Control', 'no-store, private');
    preg_match('/<pre>([0-9a-f]{48})<\/pre>/', $response->getContent(), $m);
    expect($m)->toHaveKey(1);

    $site->refresh();
    expect($site->verifiesServerKey($m[1]))->toBeTrue()
        ->and($site->server_key_hash)->not->toBe($m[1]);

    $this->get(route('stats.sites.index'))->assertDontSee($m[1])->assertSee('Rotate server key');

    $this->post(route('stats.sites.key', ['publicId' => $site->public_id]));
    expect($site->refresh()->verifiesServerKey($m[1]))->toBeFalse();
});

it('creates a site with language versions, excluded paths and public counts', function () {
    $this->post(route('stats.sites.store'), [
        'name' => 'Docs',
        'domains' => 'example.com',
        'timezone' => 'UTC',
        'locales' => "EN\r\nzh-CN\n\nen\npt-br",
        'default_locale' => ' EN ',
        'exclude_paths' => "/admin/*\n\n/Preview\n/admin/*",
        'public_counts' => '1',
    ])->assertRedirect(route('stats.sites.index'));

    $site = Site::query()->sole();
    expect($site->locales)->toBe(['en', 'zh-cn', 'pt-br'])
        ->and($site->default_locale)->toBe('en')
        ->and($site->exclude_paths)->toBe(['/admin/*', '/Preview'])
        ->and($site->public_counts)->toBeTrue();
});

it('stores empty language versions and excluded paths, no default locale and public counts off', function () {
    $site = S::site('Docs', ['locales' => ['en'], 'default_locale' => 'en', 'exclude_paths' => ['/x'], 'public_counts' => true]);

    $this->put(route('stats.sites.update', ['publicId' => $site->public_id]), [
        'name' => 'Docs', 'domains' => 'example.com', 'timezone' => 'UTC', 'locales' => '', 'default_locale' => '', 'exclude_paths' => " \n",
    ])->assertRedirect(route('stats.sites.index'));

    $site->refresh();
    expect($site->locales)->toBe([])
        ->and($site->default_locale)->toBeNull()
        ->and($site->exclude_paths)->toBe([])
        ->and($site->public_counts)->toBeFalse();
});

it('rejects invalid language versions, default locale and excluded paths', function (array $input, string $field, string $message) {
    $this->followingRedirects()->from(route('stats.sites.index'))
        ->post(route('stats.sites.store'), $input + ['name' => 'X', 'domains' => 'example.com', 'timezone' => 'UTC'])
        ->assertSee($message, false);
    $this->from(route('stats.sites.index'))
        ->post(route('stats.sites.store'), $input + ['name' => 'X', 'domains' => 'example.com', 'timezone' => 'UTC'])
        ->assertSessionHasErrorsIn('create', $field);
    expect(Site::query()->count())->toBe(0);
})->with([
    'locale word' => [['locales' => 'english'], 'locales', '“english” is not a language code'],
    'locale with underscore' => [['locales' => "en\nzh_cn"], 'locales', '“zh_cn” is not a language code'],
    'locale region too long' => [['locales' => 'en-abcdefghi'], 'locales', '“en-abcdefghi” is not a language code'],
    'too many locales' => [['locales' => implode("\n", array_map(fn ($i) => 'l'.chr(96 + $i), range(1, 21)))], 'locales', 'Enter at most 20 language versions.'],
    'default locale' => [['default_locale' => 'en_US'], 'default_locale', '“en_us” is not a language code'],
    'path without slash' => [['exclude_paths' => 'admin/*'], 'exclude_paths', '“admin/*” must start with / and be at most 200 characters long.'],
    'path too long' => [['exclude_paths' => '/'.str_repeat('a', 200)], 'exclude_paths', 'must start with / and be at most 200 characters long.'],
    'too many paths' => [['exclude_paths' => implode("\n", array_map(fn ($i) => "/p{$i}", range(1, 51)))], 'exclude_paths', 'Enter at most 50 excluded paths.'],
]);

it('shows the new fields with the current values and explains public counts', function () {
    S::site('Docs', ['locales' => ['en', 'zh-cn'], 'default_locale' => 'en', 'exclude_paths' => ['/admin/*'], 'public_counts' => true]);

    $this->get(route('stats.sites.index'))
        ->assertOk()
        ->assertSee("en\nzh-cn", false)
        ->assertSee('name="default_locale" maxlength="16" value="en"', false)
        ->assertSee('/admin/*')
        ->assertSee('name="public_counts" value="1" aria-describedby', false)
        ->assertSee('checked', false)
        ->assertSee('public counts on')
        ->assertSee('Lets anyone, without a token, read the all-time pageview counts');
});

it('keeps the entered values after a validation error', function () {
    $this->followingRedirects()->from(route('stats.sites.index'))
        ->post(route('stats.sites.store'), ['name' => 'X', 'domains' => 'example.com', 'timezone' => 'UTC', 'locales' => "en\nbad_one", 'exclude_paths' => '/keep-me', 'public_counts' => '1'])
        ->assertSee("en\nbad_one", false)
        ->assertSee('/keep-me')
        ->assertSee('aria-invalid="true"', false);
});
