<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use N2ns\Stats\Models\Site;

const COUNTRY_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

/** Sends a pageview from $ip with extra server variables and returns the country it was counted under. */
function countedCountry(string $ip, array $server = []): string
{
    $site = Site::query()->where('name', 'Example')->sole();
    DB::table('stats_visits')->delete();
    test()->call('POST', '/api/event', [], [], [], array_merge([
        'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        'HTTP_ORIGIN' => 'https://example.com',
        'HTTP_USER_AGENT' => COUNTRY_UA,
        'REMOTE_ADDR' => $ip,
    ], $server), json_encode(['s' => $site->public_id, 't' => 'pageview', 'u' => 'https://example.com/a']))->assertNoContent();

    return json_decode((string) DB::table('stats_visits')->sole()->dimensions, true)['country'];
}

beforeEach(function () {
    Site::create(['name' => 'Example', 'domains' => ['example.com']]);
    config(['stats.country_database' => __DIR__.'/../fixtures/country-test.mmdb']);
});

it('looks the country up in the local database when there is no country header', function () {
    expect(countedCountry('198.51.100.7'))->toBe('FR')
        ->and(countedCountry('2001:db8::1'))->toBe('JP');
});

it('prefers the country header and falls back to the database for an unknown code', function () {
    expect(countedCountry('198.51.100.7', ['HTTP_CF_IPCOUNTRY' => 'DE']))->toBe('DE');
    expect(countedCountry('198.51.100.8', ['HTTP_CF_IPCOUNTRY' => 'XX']))->toBe('FR');
});

it('reads the configured country header', function () {
    config(['stats.country_header' => 'CloudFront-Viewer-Country', 'stats.country_database' => null]);

    expect(countedCountry('203.0.113.5', ['HTTP_CLOUDFRONT_VIEWER_COUNTRY' => 'NL', 'HTTP_CF_IPCOUNTRY' => 'DE']))->toBe('NL');
});

it('counts unknown for addresses the database has no country for', function () {
    expect(countedCountry('192.0.2.1'))->toBe('unknown');
    expect(countedCountry('203.0.113.5'))->toBe('unknown');
});

it('still records the pageview when the database cannot be read', function () {
    config(['stats.country_database' => __DIR__.'/../fixtures/missing.mmdb']);
    Log::spy();

    expect(countedCountry('198.51.100.7'))->toBe('unknown')
        ->and((int) DB::table('stats_daily_totals')->value('pageviews'))->toBe(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'country database'));
});

it('looks up the forwarded address of a server event from a client app', function () {
    $site = Site::create(['name' => 'Client', 'domains' => ['client.com'], 'server_key_hash' => hash('sha256', 'secret-key')]);

    test()->call('POST', '/api/server-event', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer secret-key'], json_encode([
        'site' => $site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/a', 'ip' => '198.51.100.9', 'user_agent' => COUNTRY_UA,
    ]))->assertNoContent();

    expect(json_decode((string) DB::table('stats_visits')->value('dimensions'), true)['country'])->toBe('FR');
});
