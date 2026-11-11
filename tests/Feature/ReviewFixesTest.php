<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\StatsQuery;

const REVIEW_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

function reviewSend(Site $site, string $url, array $server = []): void
{
    test()->call('POST', '/api/event', [], [], [], array_merge([
        'CONTENT_TYPE' => 'text/plain',
        'HTTP_USER_AGENT' => REVIEW_UA,
        'REMOTE_ADDR' => '203.0.113.5',
    ], $server), json_encode(['s' => $site->public_id, 't' => 'pageview', 'u' => $url]))->assertNoContent();
}

beforeEach(function () {
    $this->site = Site::create(['name' => 'Example', 'domains' => ['example.com']]);
});

it('accepts beacons with Origin: null and no Referer (strict referrer policies)', function () {
    reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'null']);
    reviewSend($this->site, 'https://example.com/b', []);

    expect((int) DB::table('stats_daily_totals')->value('pageviews'))->toBe(2);
});

it('still rejects a foreign Referer when Origin is null', function () {
    reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'null', 'HTTP_REFERER' => 'https://evil.com/']);

    expect(DB::table('stats_daily_totals')->count())->toBe(0);
});

it('limits IPv6 clients per /64 block', function () {
    config(['stats.rate_limits.per_ip_per_minute' => 2]);
    foreach (['2001:db8:1:2::1', '2001:db8:1:2::2', '2001:db8:1:2:ffff::9'] as $ip) {
        reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'https://example.com', 'REMOTE_ADDR' => $ip]);
    }

    expect((int) DB::table('stats_daily_totals')->value('pageviews'))->toBe(2);
});

it('limits each site per minute', function () {
    config(['stats.rate_limits.per_site_per_minute' => 1]);
    reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'https://example.com', 'REMOTE_ADDR' => '198.51.100.1']);
    reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'https://example.com', 'REMOTE_ADDR' => '198.51.100.2']);

    expect((int) DB::table('stats_daily_totals')->value('pageviews'))->toBe(1);
});

it('does not store the raw or plainly hashed IP in rate limit keys', function () {
    reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'https://example.com']);

    $limiter = app(RateLimiter::class);
    expect($limiter->attempts('stats-collect-ip:'.hash('sha256', '203.0.113.5')))->toBe(0);
});

it('counts new values beyond the daily row cap under (other)', function () {
    config(['stats.max_new_rows_per_day' => 2]);
    foreach (['/a', '/b', '/c', '/d'] as $i => $path) {
        reviewSend($this->site, 'https://example.com'.$path, ['HTTP_ORIGIN' => 'https://example.com', 'REMOTE_ADDR' => '198.51.100.'.($i + 10)]);
    }

    // Compared without order: how '(other)' sorts against '/a' depends on the database collation.
    $pages = DB::table('stats_daily_pages')->pluck('pageviews', 'path')->map(fn ($v) => (int) $v)->all();
    ksort($pages);
    expect($pages)->toBe(['(other)' => 2, '/a' => 1, '/b' => 1]);
});

it('counts realtime visitors correctly when the app timezone is not UTC', function () {
    config(['app.timezone' => 'Asia/Shanghai']);
    date_default_timezone_set('Asia/Shanghai');
    try {
        reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'https://example.com']);
        expect(app(StatsQuery::class)->realtimeVisitors($this->site))->toBe(1);
        $this->travel(6)->minutes();
        expect(app(StatsQuery::class)->realtimeVisitors($this->site))->toBe(0);
    } finally {
        date_default_timezone_set('UTC');
    }
});

it('logs only the exception class when a write fails', function () {
    failWritesTo('stats_daily_pages');
    Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
        return ! str_contains(json_encode($context), 'stats_daily_pages') && str_contains($context['exception'], 'Exception');
    });

    reviewSend($this->site, 'https://example.com/a', ['HTTP_ORIGIN' => 'https://example.com']);
});
