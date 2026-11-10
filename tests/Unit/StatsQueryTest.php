<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use N2ns\Stats\Reports\StatsQuery;
use N2ns\Stats\Tests\StatsSeeder as S;

function d(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

beforeEach(function () {
    $this->q = new StatsQuery;
    $this->site = S::site();
});

it('sums the range and derives per-visit metrics', function () {
    S::totals($this->site, '2026-03-01', ['visitors' => 10, 'visits' => 12, 'pageviews' => 30, 'bounces' => 6, 'duration_seconds' => 600, 'events' => 4]);
    S::totals($this->site, '2026-03-02', ['visitors' => 5, 'visits' => 8, 'pageviews' => 10, 'bounces' => 2, 'duration_seconds' => 400, 'events' => 1]);
    S::totals($this->site, '2026-03-03', ['visitors' => 99, 'visits' => 99, 'pageviews' => 99]); // outside
    S::totals(S::site('Other'), '2026-03-01', ['visitors' => 77, 'visits' => 77, 'pageviews' => 77]); // other site

    $s = $this->q->summary($this->site, d('2026-03-01'), d('2026-03-02'));

    expect($s)->toMatchArray([
        'visitors' => 15, 'visits' => 20, 'pageviews' => 40, 'views_per_visit' => 2.0,
        'bounce_rate' => 0.4, 'avg_duration_seconds' => 50, 'events' => 5,
    ]);
});

it('compares with the previous period of equal length', function () {
    S::totals($this->site, '2026-02-26', ['visitors' => 1, 'visits' => 1, 'pageviews' => 1]); // before previous
    S::totals($this->site, '2026-02-27', ['visitors' => 3, 'visits' => 4, 'pageviews' => 9, 'bounces' => 1, 'duration_seconds' => 80]);
    S::totals($this->site, '2026-02-28', ['visitors' => 2, 'visits' => 2, 'pageviews' => 3]);
    S::totals($this->site, '2026-03-01', ['visitors' => 10, 'visits' => 10, 'pageviews' => 10]);

    $s = $this->q->summary($this->site, d('2026-03-01'), d('2026-03-02'));

    expect($s['visitors'])->toBe(10)
        ->and($s['previous'])->toBe([
            'visitors' => 5, 'visits' => 6, 'pageviews' => 12, 'views_per_visit' => 2.0,
            'bounce_rate' => 0.1667, 'avg_duration_seconds' => 13, 'events' => 0,
        ]);
});

it('returns zeros without division errors when there is no data', function () {
    $s = $this->q->summary($this->site, d('2026-03-01'), d('2026-03-01'));

    expect($s['visits'])->toBe(0)
        ->and($s['views_per_visit'])->toBe(0.0)
        ->and($s['bounce_rate'])->toBe(0.0)
        ->and($s['avg_duration_seconds'])->toBe(0)
        ->and($s['previous']['visitors'])->toBe(0);
});

it('zero-fills the daily series in site-local dates', function () {
    S::totals($this->site, '2026-02-27', ['visitors' => 2, 'visits' => 3, 'pageviews' => 4]);
    S::totals($this->site, '2026-03-01', ['visitors' => 5, 'visits' => 6, 'pageviews' => 7]);

    $series = $this->q->timeseries($this->site, d('2026-02-27'), d('2026-03-02'));

    expect($series)->toBe([
        ['date' => '2026-02-27', 'visitors' => 2, 'visits' => 3, 'pageviews' => 4],
        ['date' => '2026-02-28', 'visitors' => 0, 'visits' => 0, 'pageviews' => 0],
        ['date' => '2026-03-01', 'visitors' => 5, 'visits' => 6, 'pageviews' => 7],
        ['date' => '2026-03-02', 'visitors' => 0, 'visits' => 0, 'pageviews' => 0],
    ]);
});

it('aggregates pages, entries and exits across days', function () {
    S::page($this->site, '2026-03-01', '/a', ['pageviews' => 5, 'visitors' => 3, 'entries' => 2, 'exits' => 0]);
    S::page($this->site, '2026-03-02', '/a', ['pageviews' => 4, 'visitors' => 2, 'entries' => 1, 'exits' => 0]);
    S::page($this->site, '2026-03-01', '/b', ['pageviews' => 7, 'visitors' => 7, 'entries' => 0, 'exits' => 6]);
    S::page($this->site, '2026-03-05', '/c', ['pageviews' => 50, 'visitors' => 50, 'entries' => 50, 'exits' => 50]);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'pages', $from, $to))->toBe([
        ['path' => '/a', 'pageviews' => 9, 'visitors' => 5, 'entries' => 3, 'exits' => 0],
        ['path' => '/b', 'pageviews' => 7, 'visitors' => 7, 'entries' => 0, 'exits' => 6],
    ])
        ->and($this->q->breakdown($this->site, 'entries', $from, $to))->toBe([['path' => '/a', 'entries' => 3, 'visitors' => 5]])
        ->and($this->q->breakdown($this->site, 'exits', $from, $to))->toBe([['path' => '/b', 'exits' => 6]])
        ->and($this->q->breakdown($this->site, 'pages', $from, $to, 1))->toHaveCount(1);
});

it('reports sources, source types and campaigns', function () {
    $utm = ['utm_medium' => 'email', 'utm_campaign' => 'launch', 'utm_content' => null, 'utm_term' => null];
    S::source($this->site, '2026-03-01', 'search', 'google.com', ['visits' => 10, 'visitors' => 8, 'pageviews' => 20, 'bounces' => 5]);
    S::source($this->site, '2026-03-02', 'search', 'google.com', ['visits' => 10, 'visitors' => 9, 'pageviews' => 15, 'bounces' => 3]);
    S::source($this->site, '2026-03-01', 'search', 'bing.com', ['visits' => 2, 'visitors' => 2, 'pageviews' => 2, 'bounces' => 2]);
    S::source($this->site, '2026-03-01', 'direct', null, ['visits' => 4, 'visitors' => 4, 'pageviews' => 4, 'bounces' => 0]);
    S::source($this->site, '2026-03-01', 'utm', 'newsletter', ['visits' => 3, 'visitors' => 3, 'pageviews' => 6, 'bounces' => 1], $utm);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'sources', $from, $to))->toBe([
        ['source_type' => 'search', 'source' => 'google.com', 'visits' => 20, 'visitors' => 17, 'pageviews' => 35, 'bounce_rate' => 0.4],
        ['source_type' => 'direct', 'source' => null, 'visits' => 4, 'visitors' => 4, 'pageviews' => 4, 'bounce_rate' => 0.0],
        ['source_type' => 'utm', 'source' => 'newsletter', 'visits' => 3, 'visitors' => 3, 'pageviews' => 6, 'bounce_rate' => 0.3333],
        ['source_type' => 'search', 'source' => 'bing.com', 'visits' => 2, 'visitors' => 2, 'pageviews' => 2, 'bounce_rate' => 1.0],
    ])
        ->and($this->q->breakdown($this->site, 'source_types', $from, $to))->toBe([
            ['source_type' => 'search', 'visits' => 22, 'visitors' => 19],
            ['source_type' => 'direct', 'visits' => 4, 'visitors' => 4],
            ['source_type' => 'utm', 'visits' => 3, 'visitors' => 3],
        ])
        ->and($this->q->breakdown($this->site, 'campaigns', $from, $to))->toBe([
            ['source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'launch', 'utm_content' => null, 'utm_term' => null, 'visits' => 3, 'visitors' => 3],
        ]);
});

it('reports each visitor dimension', function (string $report, string $dimension) {
    S::dimension($this->site, '2026-03-01', $dimension, 'b', 2, 2);
    S::dimension($this->site, '2026-03-01', $dimension, 'a', 5, 4);
    S::dimension($this->site, '2026-03-02', $dimension, 'b', 4, 3);
    S::dimension($this->site, '2026-03-01', $dimension === 'os' ? 'device' : 'os', 'zzz', 99, 99);

    expect($this->q->breakdown($this->site, $report, d('2026-03-01'), d('2026-03-02')))->toBe([
        ['value' => 'b', 'visits' => 6, 'visitors' => 5],
        ['value' => 'a', 'visits' => 5, 'visitors' => 4],
    ]);
})->with([
    ['countries', 'country'], ['devices', 'device'], ['browsers', 'browser'], ['os', 'os'], ['languages', 'language'],
]);

it('reports outbound links, downloads and custom events', function () {
    S::event($this->site, '2026-03-01', 'outbound', 'outbound', 'https://x.com/a', '/p1', 3, 2);
    S::event($this->site, '2026-03-01', 'outbound', 'outbound', 'https://x.com/a', '/p2', 2, 2);
    S::event($this->site, '2026-03-01', 'download', 'download', 'https://example.com/f.pdf', '/p1', 4, 3);
    S::event($this->site, '2026-03-01', 'custom', 'signup', 'hero', '/p1', 6, 5);
    S::event($this->site, '2026-03-02', 'custom', 'signup', 'hero', '/p2', 1, 1);
    S::event($this->site, '2026-03-02', 'custom', 'signup', null, '/p2', 2, 2);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'outbound', $from, $to))->toBe([['target' => 'https://x.com/a', 'count' => 5, 'visitors' => 4]])
        ->and($this->q->breakdown($this->site, 'downloads', $from, $to))->toBe([['target' => 'https://example.com/f.pdf', 'count' => 4, 'visitors' => 3]])
        ->and($this->q->breakdown($this->site, 'events', $from, $to))->toBe([
            ['name' => 'signup', 'label' => 'hero', 'count' => 7, 'visitors' => 6],
            ['name' => 'signup', 'label' => null, 'count' => 2, 'visitors' => 2],
        ]);
});

it('combines AI referrals and AI fetches', function () {
    S::source($this->site, '2026-03-01', 'ai', 'chatgpt.com', ['visits' => 4, 'visitors' => 4, 'pageviews' => 4, 'bounces' => 0]);
    S::source($this->site, '2026-03-01', 'search', 'google.com', ['visits' => 40, 'visitors' => 40, 'pageviews' => 40, 'bounces' => 0]);
    S::event($this->site, '2026-03-01', 'ai_fetch', 'gptbot', null, '/docs', 9);
    S::event($this->site, '2026-03-02', 'ai_fetch', 'gptbot', null, '/docs', 1);
    S::event($this->site, '2026-03-01', 'ai_fetch', 'claudebot', null, '/', 2);

    $rows = $this->q->breakdown($this->site, 'ai', d('2026-03-01'), d('2026-03-02'));

    expect($rows)->toBe([
        ['kind' => 'fetch', 'name' => 'gptbot', 'path' => '/docs', 'count' => 10],
        ['kind' => 'referral', 'name' => 'chatgpt.com', 'path' => null, 'count' => 4],
        ['kind' => 'fetch', 'name' => 'claudebot', 'path' => '/', 'count' => 2],
    ])
        ->and($this->q->breakdown($this->site, 'ai', d('2026-03-01'), d('2026-03-02'), 2))->toHaveCount(2);
});

it('rejects unknown report names', function () {
    $this->q->breakdown($this->site, 'secrets', d('2026-03-01'), d('2026-03-01'));
})->throws(InvalidArgumentException::class);

it('counts visits active in the last five minutes', function () {
    Carbon::setTestNow('2026-03-01 12:00:00');
    S::visit($this->site, 'a', now()->subMinutes(1));
    S::visit($this->site, 'b', now()->subMinutes(4));
    S::visit($this->site, 'c', now()->subMinutes(6));
    S::visit(S::site('Other'), 'd', now());

    expect($this->q->realtimeVisitors($this->site))->toBe(2);
});

it('returns rows with exactly the documented columns', function (string $report) {
    S::page($this->site, '2026-03-01', '/', ['pageviews' => 1, 'visitors' => 1, 'entries' => 1, 'exits' => 1]);
    S::source($this->site, '2026-03-01', 'utm', 'x', ['visits' => 1, 'visitors' => 1, 'pageviews' => 1, 'bounces' => 0], ['utm_medium' => 'm', 'utm_campaign' => 'c', 'utm_content' => null, 'utm_term' => null]);
    foreach (['country', 'device', 'browser', 'os', 'language'] as $dimension) {
        S::dimension($this->site, '2026-03-01', $dimension, 'v', 1, 1);
    }
    foreach (['outbound', 'download', 'custom', 'ai_fetch'] as $kind) {
        S::event($this->site, '2026-03-01', $kind, $kind, 't', '/', 1, 1);
    }

    $rows = $this->q->breakdown($this->site, $report, d('2026-03-01'), d('2026-03-01'));

    expect($rows)->not->toBeEmpty()
        ->and(array_keys($rows[0]))->toBe(StatsQuery::COLUMNS[$report]);
})->with(StatsQuery::REPORTS);
