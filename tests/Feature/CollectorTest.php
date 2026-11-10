<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use N2ns\Stats\Models\Site;

const COLLECT_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

/** @param  array<string, mixed>|string  $payload */
function sendEvent(array|string $payload, array $server = []): TestResponse
{
    return test()->call('POST', '/api/event', [], [], [], array_merge([
        'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        'HTTP_ORIGIN' => 'https://example.com',
        'HTTP_USER_AGENT' => COLLECT_UA,
        'REMOTE_ADDR' => '203.0.113.5',
    ], $server), is_string($payload) ? $payload : json_encode($payload));
}

function pageviewFor(Site $site, array $overrides = []): array
{
    return array_merge(['s' => $site->public_id, 't' => 'pageview', 'u' => 'https://example.com/a?x=1'], $overrides);
}

beforeEach(function () {
    $this->site = Site::create(['name' => 'Example', 'domains' => ['example.com', '*.example.org']]);
});

it('records a pageview with its dimensions and sets no cookies', function () {
    $response = sendEvent(pageviewFor($this->site, ['w' => 1920]), [
        'HTTP_CF_IPCOUNTRY' => 'DE',
        'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en;q=0.8',
    ]);

    $response->assertNoContent();
    expect($response->headers->getCookies())->toBe([]);

    $totals = DB::table('stats_daily_totals')->first();
    expect([(int) $totals->pageviews, (int) $totals->visitors, (int) $totals->visits])->toBe([1, 1, 1]);

    $page = DB::table('stats_daily_pages')->first();
    expect($page->path)->toBe('/a')
        ->and([(int) $page->pageviews, (int) $page->visitors, (int) $page->entries])->toBe([1, 1, 1]);

    $dims = DB::table('stats_daily_dimensions')->pluck('value', 'dimension')->all();
    expect($dims)->toEqual(['country' => 'DE', 'device' => 'desktop', 'browser' => 'Chrome', 'os' => 'Windows', 'language' => 'de']);
});

it('accepts application/json and wildcard subdomains', function () {
    sendEvent(pageviewFor($this->site, ['u' => 'https://shop.example.org/']), ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://shop.example.org'])
        ->assertNoContent();
    expect(DB::table('stats_daily_totals')->value('pageviews'))->toEqual(1);
});

it('answers 400 only for malformed bodies', function () {
    sendEvent('{')->assertStatus(400);
    sendEvent(['s' => $this->site->public_id, 't' => 'nope', 'u' => 'https://example.com/'])->assertStatus(400);
    sendEvent(pageviewFor($this->site, ['r' => 'https://a.com/'.str_repeat('a', 4100)]))->assertStatus(400);
    sendEvent(pageviewFor($this->site), ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'])->assertStatus(400);
    expect(DB::table('stats_daily_totals')->count())->toBe(0);
});

it('drops rejected payloads with 204', function (array $payload, array $server) {
    sendEvent(array_merge(pageviewFor($this->site), $payload), $server)->assertNoContent();
    expect(DB::table('stats_daily_totals')->count())->toBe(0)
        ->and(DB::table('stats_visits')->count())->toBe(0);
})->with([
    'unknown site' => [['s' => '01JCZ0000000000000000000AB'], []],
    'page host not allowed' => [['u' => 'https://evil.com/a'], []],
    'origin not allowed' => [[], ['HTTP_ORIGIN' => 'https://evil.com']],
    'referer not allowed' => [[], ['HTTP_ORIGIN' => '', 'HTTP_REFERER' => 'https://evil.com/x']],
    'bot' => [[], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']],
    'ai fetcher' => [[], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; GPTBot/1.2)']],
    'empty user agent' => [[], ['HTTP_USER_AGENT' => '']],
    'invalid path' => [['u' => 'https://example.com//evil'], []],
]);

it('falls back to the referer header when there is no origin', function () {
    sendEvent(pageviewFor($this->site), ['HTTP_ORIGIN' => '', 'HTTP_REFERER' => 'https://www.example.com/a'])->assertNoContent();
    expect(DB::table('stats_daily_totals')->value('pageviews'))->toEqual(1);
});

it('drops localhost and ip hosts even when listed', function () {
    $local = Site::create(['name' => 'Local', 'domains' => ['localhost', '127.0.0.1', 'app.localhost']]);
    foreach (['http://localhost/', 'http://127.0.0.1/', 'http://app.localhost/', 'http://[::1]/'] as $url) {
        sendEvent(['s' => $local->public_id, 't' => 'pageview', 'u' => $url], ['HTTP_ORIGIN' => rtrim($url, '/')])->assertNoContent();
    }
    expect(DB::table('stats_daily_totals')->count())->toBe(0);
});

it('rate limits per ip', function () {
    config(['stats.rate_limits.per_ip_per_minute' => 2]);
    foreach (['UA one', 'UA two', 'UA three'] as $ua) {
        sendEvent(pageviewFor($this->site), ['HTTP_USER_AGENT' => COLLECT_UA.' '.$ua])->assertNoContent();
    }
    expect(DB::table('stats_daily_totals')->value('pageviews'))->toEqual(2);

    sendEvent(pageviewFor($this->site), ['REMOTE_ADDR' => '203.0.113.6'])->assertNoContent();
    expect(DB::table('stats_daily_totals')->value('pageviews'))->toEqual(3);
});

it('rate limits per visitor', function () {
    config(['stats.rate_limits.per_visitor_per_minute' => 2]);
    foreach (range(1, 3) as $i) {
        sendEvent(pageviewFor($this->site))->assertNoContent();
    }
    expect(DB::table('stats_daily_totals')->value('pageviews'))->toEqual(2);

    $this->travel(61)->seconds();
    sendEvent(pageviewFor($this->site))->assertNoContent();
    expect(DB::table('stats_daily_totals')->value('pageviews'))->toEqual(3);
});

it('records unknown country and language when headers are missing or reserved', function (string $country) {
    sendEvent(pageviewFor($this->site), ['HTTP_CF_IPCOUNTRY' => $country, 'HTTP_ACCEPT_LANGUAGE' => '*']);
    $dims = DB::table('stats_daily_dimensions')->pluck('value', 'dimension')->all();
    expect($dims['country'])->toBe('unknown')->and($dims['language'])->toBe('unknown');
})->with(['', 'XX', 'T1', 'de', 'USA']);

it('logs a warning instead of failing when the write fails', function () {
    Schema::drop('stats_daily_totals');
    Log::shouldReceive('warning')->once();

    sendEvent(pageviewFor($this->site))->assertNoContent();
});
