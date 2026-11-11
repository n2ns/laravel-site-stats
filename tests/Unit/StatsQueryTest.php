<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    S::page($this->site, '2026-03-01', '/a', ['pageviews' => 3, 'exits' => 12, 'entry_pageviews' => 30]);
    S::page($this->site, '2026-03-02', '/a', ['pageviews' => 1, 'exits' => 8, 'entry_pageviews' => 10]);
    S::page($this->site, '2026-03-02', '/b', ['pageviews' => 1]);
    S::page($this->site, '2026-03-02', '/only-entry', ['pageviews' => 0]);

    $s = $this->q->summary($this->site, d('2026-03-01'), d('2026-03-02'));

    expect($s)->toMatchArray([
        'visitors' => 15, 'visits' => 20, 'pageviews' => 40, 'pages' => 2, 'views_per_visit' => 2.0,
        'bounce_rate' => 0.4, 'avg_duration_seconds' => 50, 'events' => 5, 'outbound' => 0, 'partial' => false,
    ]);
});

it('derives views per visit from the ended visits only, not from page views without visit data', function () {
    S::totals($this->site, '2026-03-01', ['visitors' => 900, 'visits' => 0, 'pageviews' => 1000]); // imported day without visits
    S::totals($this->site, '2026-03-02', ['visitors' => 4, 'visits' => 5, 'pageviews' => 15]);
    S::page($this->site, '2026-03-01', '/', ['pageviews' => 1000]);
    // The day counting began: 400 imported page views, then 5 visits with 15 page views, all ended.
    S::page($this->site, '2026-03-02', '/', ['pageviews' => 415, 'entries' => 5, 'exits' => 5, 'entry_pageviews' => 15]);
    DB::table('stats_daily_totals')->where('date', '2026-03-02')->update(['pageviews' => 415]);

    $s = $this->q->summary($this->site, d('2026-03-01'), d('2026-03-02'));

    expect($s)->toMatchArray(['visits' => 5, 'pageviews' => 1415, 'views_per_visit' => 3.0])
        ->and(collect($this->q->timeseries($this->site, d('2026-03-01'), d('2026-03-02')))->pluck('views_per_visit')->all())->toBe([0.0, 3.0]);
});

it('compares with the previous period of equal length', function () {
    S::totals($this->site, '2026-02-26', ['visitors' => 1, 'visits' => 1, 'pageviews' => 1]); // before previous
    S::totals($this->site, '2026-02-27', ['visitors' => 3, 'visits' => 4, 'pageviews' => 9, 'bounces' => 1, 'duration_seconds' => 80]);
    S::totals($this->site, '2026-02-28', ['visitors' => 2, 'visits' => 2, 'pageviews' => 3]);
    S::totals($this->site, '2026-03-01', ['visitors' => 10, 'visits' => 10, 'pageviews' => 10]);
    S::page($this->site, '2026-02-27', '/a', ['pageviews' => 0, 'exits' => 4, 'entry_pageviews' => 9]);
    S::page($this->site, '2026-02-28', '/a', ['pageviews' => 0, 'exits' => 2, 'entry_pageviews' => 3]);

    $s = $this->q->summary($this->site, d('2026-03-01'), d('2026-03-02'));

    expect($s['visitors'])->toBe(10)
        ->and($s['comparison'])->toMatchArray([
            'current_from' => '2026-03-01', 'current_to' => '2026-03-02', 'previous_from' => '2026-02-27', 'previous_to' => '2026-02-28',
        ])
        ->and($s['comparison']['current']['visitors'])->toBe(10)
        ->and($s['comparison']['previous'])->toBe([
            'visitors' => 5, 'visits' => 6, 'pageviews' => 12, 'pages' => 0, 'views_per_visit' => 2.0,
            'bounce_rate' => 0.1667, 'avg_duration_seconds' => 13, 'avg_page_seconds' => 0, 'events' => 0, 'outbound' => 0,
        ]);
});

it('compares complete days only when the range includes today', function () {
    Carbon::setTestNow('2026-03-10 15:00:00');
    S::totals($this->site, '2026-03-03', ['visitors' => 100]); // before the previous window
    S::totals($this->site, '2026-03-04', ['visitors' => 1]);
    S::totals($this->site, '2026-03-06', ['visitors' => 2]);
    S::totals($this->site, '2026-03-07', ['visitors' => 4]);
    S::totals($this->site, '2026-03-09', ['visitors' => 8]);
    S::totals($this->site, '2026-03-10', ['visitors' => 16]); // today, still in progress

    $s = $this->q->summary($this->site, d('2026-03-07'), d('2026-03-10'));

    expect($s['visitors'])->toBe(28)
        ->and($s['partial'])->toBeTrue()
        ->and($s['comparison'])->toMatchArray(['current_from' => '2026-03-07', 'current_to' => '2026-03-09', 'previous_from' => '2026-03-04', 'previous_to' => '2026-03-06'])
        ->and($s['comparison']['current']['visitors'])->toBe(12)
        ->and($s['comparison']['previous']['visitors'])->toBe(3);

    $today = $this->q->summary($this->site, d('2026-03-10'), d('2026-03-10'));
    expect($today['comparison'])->toMatchArray(['current_from' => '2026-03-09', 'current_to' => '2026-03-09', 'previous_from' => '2026-03-08', 'previous_to' => '2026-03-08'])
        ->and($today['comparison']['current']['visitors'])->toBe(8)
        ->and($today['comparison']['previous']['visitors'])->toBe(0);
});

it('returns zeros without division errors when there is no data', function () {
    $s = $this->q->summary($this->site, d('2026-03-01'), d('2026-03-01'));

    expect($s['visits'])->toBe(0)
        ->and($s['views_per_visit'])->toBe(0.0)
        ->and($s['bounce_rate'])->toBe(0.0)
        ->and($s['avg_duration_seconds'])->toBe(0)
        ->and($s['comparison']['previous']['visitors'])->toBe(0);
});

it('zero-fills the daily series in site-local dates', function () {
    S::totals($this->site, '2026-02-27', ['visitors' => 2, 'visits' => 3, 'pageviews' => 4, 'events' => 3, 'outbound' => 1, 'bounces' => 1, 'duration_seconds' => 120]);
    S::page($this->site, '2026-02-27', '/a', ['pageviews' => 3, 'engaged_views' => 2, 'engaged_seconds' => 30, 'exits' => 3, 'entry_pageviews' => 4]);
    S::page($this->site, '2026-02-27', '/b', ['pageviews' => 1]);
    S::page($this->site, '2026-02-27', '/entry-only', ['pageviews' => 0]);
    S::totals($this->site, '2026-03-01', ['visitors' => 5, 'visits' => 6, 'pageviews' => 7]);
    S::page($this->site, '2026-03-01', '/ended', ['pageviews' => 0, 'exits' => 6, 'entry_pageviews' => 7]);

    $series = $this->q->timeseries($this->site, d('2026-02-27'), d('2026-03-02'));

    expect($series)->toBe([
        ['date' => '2026-02-27', 'visitors' => 2, 'visits' => 3, 'pageviews' => 4, 'events' => 3, 'outbound' => 1, 'pages' => 2, 'views_per_visit' => 1.33, 'bounce_rate' => 0.3333, 'avg_duration_seconds' => 40, 'avg_page_seconds' => 15],
        ['date' => '2026-02-28', 'visitors' => 0, 'visits' => 0, 'pageviews' => 0, 'events' => 0, 'outbound' => 0, 'pages' => 0, 'views_per_visit' => 0.0, 'bounce_rate' => 0.0, 'avg_duration_seconds' => 0, 'avg_page_seconds' => 0],
        ['date' => '2026-03-01', 'visitors' => 5, 'visits' => 6, 'pageviews' => 7, 'events' => 0, 'outbound' => 0, 'pages' => 0, 'views_per_visit' => 1.17, 'bounce_rate' => 0.0, 'avg_duration_seconds' => 0, 'avg_page_seconds' => 0],
        ['date' => '2026-03-02', 'visitors' => 0, 'visits' => 0, 'pageviews' => 0, 'events' => 0, 'outbound' => 0, 'pages' => 0, 'views_per_visit' => 0.0, 'bounce_rate' => 0.0, 'avg_duration_seconds' => 0, 'avg_page_seconds' => 0],
    ]);
});

it('aggregates pages, entries and exits across days', function () {
    S::page($this->site, '2026-03-01', '/a', ['pageviews' => 5, 'visitors' => 4, 'entries' => 2, 'entry_visitors' => 2, 'exits' => 0, 'entry_pageviews' => 6, 'entry_outbound' => 1, 'entry_events' => 2]);
    S::page($this->site, '2026-03-02', '/a', ['pageviews' => 4, 'visitors' => 2, 'entries' => 1, 'entry_visitors' => 1, 'exits' => 0, 'entry_pageviews' => 2, 'title' => 'Home', 'page_type' => 'home']);
    S::page($this->site, '2026-03-01', '/b', ['pageviews' => 7, 'visitors' => 7, 'entries' => 0, 'exits' => 6]);
    S::page($this->site, '2026-03-05', '/c', ['pageviews' => 50, 'visitors' => 50, 'entries' => 50, 'exits' => 50]);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'pages', $from, $to))->toBe([
        ['path' => '/a', 'title' => 'Home', 'page_type' => 'home', 'pageviews' => 9, 'visitors' => 6, 'entries' => 3, 'exits' => 0],
        ['path' => '/b', 'title' => null, 'page_type' => null, 'pageviews' => 7, 'visitors' => 7, 'entries' => 0, 'exits' => 6],
    ])
        ->and($this->q->breakdown($this->site, 'entries', $from, $to))->toBe([
            ['path' => '/a', 'title' => 'Home', 'entries' => 3, 'visitors' => 3, 'entry_pageviews' => 8, 'entry_outbound' => 1, 'entry_events' => 2],
        ])
        ->and($this->q->breakdown($this->site, 'exits', $from, $to))->toBe([['path' => '/b', 'title' => null, 'exits' => 6]])
        ->and($this->q->breakdown($this->site, 'pages', $from, $to, 1))->toHaveCount(1)
        ->and($this->q->breakdown($this->site, 'pages', $from, $to, 1, 1))->toBe([
            ['path' => '/b', 'title' => null, 'page_type' => null, 'pageviews' => 7, 'visitors' => 7, 'entries' => 0, 'exits' => 6],
        ])
        ->and($this->q->breakdown($this->site, 'pages', $from, $to, 50, 0, 7))->toHaveCount(1)
        ->and($this->q->breakdown($this->site, 'entries', $from, $to, 50, 0, 3))->toHaveCount(1)
        ->and($this->q->breakdown($this->site, 'entries', $from, $to, 50, 0, 4))->toBe([])
        ->and($this->q->total($this->site, 'pages', $from, $to))->toBe(16);
});

it('groups pages by type and by content group', function () {
    S::page($this->site, '2026-03-01', '/blog/a', ['pageviews' => 5, 'visitors' => 4, 'page_type' => 'article', 'content_group' => 'post-1', 'title' => 'A']);
    S::page($this->site, '2026-03-01', '/zh/blog/a', ['pageviews' => 3, 'visitors' => 3, 'page_type' => 'article', 'content_group' => 'post-1', 'title' => 'A']);
    S::page($this->site, '2026-03-01', '/pricing', ['pageviews' => 2, 'visitors' => 2, 'page_type' => 'product']);
    S::page($this->site, '2026-03-01', '/x', ['pageviews' => 9, 'visitors' => 9]);

    [$from, $to] = [d('2026-03-01'), d('2026-03-01')];

    expect($this->q->breakdown($this->site, 'page_types', $from, $to))->toBe([
        ['page_type' => 'article', 'pageviews' => 8, 'visitors' => 7, 'pages' => 2],
        ['page_type' => 'product', 'pageviews' => 2, 'visitors' => 2, 'pages' => 1],
    ])
        ->and($this->q->breakdown($this->site, 'content', $from, $to))->toBe([
            ['content_group' => 'post-1', 'title' => 'A', 'pageviews' => 8, 'visitors' => 7, 'pages' => 2],
        ]);
});

it('reports page-to-page moves and landing pages by source', function () {
    S::transition($this->site, '2026-03-01', '/a', '/checkout', 3, 2);
    S::transition($this->site, '2026-03-02', '/a', '/checkout', 1, 1);
    S::transition($this->site, '2026-03-01', '/b', '/a', 2, 2);
    S::entry($this->site, '2026-03-01', '/landing', 'utm', 'news', 4, 4, 'launch');
    S::entry($this->site, '2026-03-01', '/guide', 'search', 'google.com', 6, 5);
    S::entry($this->site, '2026-03-02', '/guide', 'search', 'google.com', 1, 1);
    S::entry($this->site, '2026-03-01', '/docs', 'ai', 'chatgpt.com', 2, 2);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'transitions', $from, $to))->toBe([
        ['from_path' => '/a', 'to_path' => '/checkout', 'count' => 4, 'visitors' => 3],
        ['from_path' => '/b', 'to_path' => '/a', 'count' => 2, 'visitors' => 2],
    ])
        ->and($this->q->breakdown($this->site, 'landing_pages', $from, $to))->toBe([
            ['source_type' => 'search', 'source' => 'google.com', 'utm_campaign' => null, 'path' => '/guide', 'visits' => 7, 'visitors' => 6],
            ['source_type' => 'utm', 'source' => 'news', 'utm_campaign' => 'launch', 'path' => '/landing', 'visits' => 4, 'visitors' => 4],
            ['source_type' => 'ai', 'source' => 'chatgpt.com', 'utm_campaign' => null, 'path' => '/docs', 'visits' => 2, 'visitors' => 2],
        ])
        ->and($this->q->breakdown($this->site, 'campaign_pages', $from, $to))->toBe([['source' => 'news', 'utm_campaign' => 'launch', 'path' => '/landing', 'visits' => 4, 'visitors' => 4]])
        ->and($this->q->breakdown($this->site, 'search_pages', $from, $to))->toBe([['source' => 'google.com', 'path' => '/guide', 'visits' => 7, 'visitors' => 6]])
        ->and($this->q->breakdown($this->site, 'ai_pages', $from, $to))->toBe([['source' => 'chatgpt.com', 'path' => '/docs', 'visits' => 2, 'visitors' => 2]]);
});

it('reports sources, source types and campaigns', function () {
    $utm = ['utm_medium' => 'email', 'utm_campaign' => 'launch', 'utm_content' => null, 'utm_term' => null];
    S::source($this->site, '2026-03-01', 'search', 'google.com', ['visits' => 10, 'visitors' => 8, 'pageviews' => 20, 'bounces' => 5, 'outbound' => 2, 'events' => 1]);
    S::source($this->site, '2026-03-02', 'search', 'google.com', ['visits' => 10, 'visitors' => 9, 'pageviews' => 15, 'bounces' => 3]);
    S::source($this->site, '2026-03-01', 'search', 'bing.com', ['visits' => 2, 'visitors' => 2, 'pageviews' => 2, 'bounces' => 2]);
    S::source($this->site, '2026-03-01', 'direct', null, ['visits' => 4, 'visitors' => 4, 'pageviews' => 4, 'bounces' => 0]);
    S::source($this->site, '2026-03-01', 'utm', 'newsletter', ['visits' => 3, 'visitors' => 3, 'pageviews' => 6, 'bounces' => 1, 'outbound' => 1, 'events' => 4], $utm);
    S::source($this->site, '2026-03-01', 'referral', 'old.example', ['visits' => 0, 'visitors' => 5, 'pageviews' => 5]); // imported history without visits

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'sources', $from, $to))->toBe([
        ['source_type' => 'search', 'source' => 'google.com', 'utm_medium' => null, 'visits' => 20, 'visitors' => 17, 'pageviews' => 35, 'bounce_rate' => 0.4, 'outbound' => 2, 'events' => 1],
        ['source_type' => 'referral', 'source' => 'old.example', 'utm_medium' => null, 'visits' => 0, 'visitors' => 5, 'pageviews' => 5, 'bounce_rate' => null, 'outbound' => 0, 'events' => 0],
        ['source_type' => 'direct', 'source' => null, 'utm_medium' => null, 'visits' => 4, 'visitors' => 4, 'pageviews' => 4, 'bounce_rate' => 0.0, 'outbound' => 0, 'events' => 0],
        ['source_type' => 'utm', 'source' => 'newsletter', 'utm_medium' => 'email', 'visits' => 3, 'visitors' => 3, 'pageviews' => 6, 'bounce_rate' => 0.3333, 'outbound' => 1, 'events' => 4],
        ['source_type' => 'search', 'source' => 'bing.com', 'utm_medium' => null, 'visits' => 2, 'visitors' => 2, 'pageviews' => 2, 'bounce_rate' => 1.0, 'outbound' => 0, 'events' => 0],
    ])
        ->and($this->q->breakdown($this->site, 'source_types', $from, $to))->toBe([
            ['source_type' => 'search', 'visits' => 22, 'visitors' => 19, 'pageviews' => 37, 'outbound' => 2, 'events' => 1],
            ['source_type' => 'referral', 'visits' => 0, 'visitors' => 5, 'pageviews' => 5, 'outbound' => 0, 'events' => 0],
            ['source_type' => 'direct', 'visits' => 4, 'visitors' => 4, 'pageviews' => 4, 'outbound' => 0, 'events' => 0],
            ['source_type' => 'utm', 'visits' => 3, 'visitors' => 3, 'pageviews' => 6, 'outbound' => 1, 'events' => 4],
        ])
        ->and($this->q->breakdown($this->site, 'campaigns', $from, $to))->toBe([
            ['source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'launch', 'utm_content' => null, 'utm_term' => null, 'visits' => 3, 'visitors' => 3, 'pageviews' => 6, 'outbound' => 1, 'events' => 4],
        ])
        ->and($this->q->total($this->site, 'source_types', $from, $to))->toBe(31); // visitors: sorted and shared by them, which history without visits also has
});

it('reports each visitor dimension', function (string $report, string $dimension) {
    S::dimension($this->site, '2026-03-01', $dimension, 'b', 2, 2, 5);
    S::dimension($this->site, '2026-03-01', $dimension, 'a', 5, 4, 9);
    S::dimension($this->site, '2026-03-02', $dimension, 'b', 4, 3, 4);
    S::dimension($this->site, '2026-03-01', $dimension === 'os' ? 'device' : 'os', 'zzz', 99, 99);

    expect($this->q->breakdown($this->site, $report, d('2026-03-01'), d('2026-03-02')))->toBe([
        ['value' => 'b', 'visits' => 6, 'visitors' => 5, 'pageviews' => 9],
        ['value' => 'a', 'visits' => 5, 'visitors' => 4, 'pageviews' => 9],
    ]);
})->with([
    ['countries', 'country'], ['devices', 'device'], ['browsers', 'browser'], ['os', 'os'], ['languages', 'language'], ['locales', 'locale'],
]);

it('reports outbound links, downloads and custom events', function () {
    S::event($this->site, '2026-03-01', 'outbound', '', 'https://x.com/a', '/p1', 3, 2, 'pairdock');
    S::event($this->site, '2026-03-01', 'outbound', '', 'https://x.com/a', '/p2', 2, 2, 'pairdock');
    S::event($this->site, '2026-03-01', 'download', '', 'https://example.com/f.pdf', '/p1', 4, 3);
    S::event($this->site, '2026-03-01', 'custom', 'signup', 'https://example.com/checkout', '/p1', 6, 5, 'hero');
    S::event($this->site, '2026-03-02', 'custom', 'signup', 'https://example.com/checkout', '/p2', 1, 1, 'hero');
    S::event($this->site, '2026-03-02', 'custom', 'signup', null, '/p2', 2, 2);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'outbound', $from, $to))->toBe([['target' => 'https://x.com/a', 'label' => 'pairdock', 'count' => 5, 'visitors' => 4]])
        ->and($this->q->breakdown($this->site, 'downloads', $from, $to))->toBe([['target' => 'https://example.com/f.pdf', 'label' => null, 'count' => 4, 'visitors' => 3]])
        ->and($this->q->breakdown($this->site, 'events', $from, $to))->toBe([
            ['name' => 'signup', 'label' => 'hero', 'target' => 'https://example.com/checkout', 'count' => 7, 'visitors' => 6],
            ['name' => 'signup', 'label' => null, 'target' => null, 'count' => 2, 'visitors' => 2],
        ])
        ->and($this->q->breakdown($this->site, 'event_pages', $from, $to, 2))->toBe([
            ['kind' => 'custom', 'name' => 'signup', 'label' => 'hero', 'target' => 'https://example.com/checkout', 'path' => '/p1', 'count' => 6, 'visitors' => 5],
            ['kind' => 'download', 'name' => '', 'label' => null, 'target' => 'https://example.com/f.pdf', 'path' => '/p1', 'count' => 4, 'visitors' => 3],
        ]);
});

it('reports AI referrals, fetchers and fetched pages', function () {
    S::source($this->site, '2026-03-01', 'ai', 'chatgpt.com', ['visits' => 4, 'visitors' => 4, 'pageviews' => 6, 'bounces' => 0]);
    S::source($this->site, '2026-03-02', 'ai', 'claude.ai', ['visits' => 1, 'visitors' => 1, 'pageviews' => 1, 'bounces' => 0]);
    S::source($this->site, '2026-03-01', 'search', 'google.com', ['visits' => 40, 'visitors' => 40, 'pageviews' => 40, 'bounces' => 0]);
    S::event($this->site, '2026-03-01', 'ai_fetch', 'gptbot', null, '/docs', 9);
    S::event($this->site, '2026-03-02', 'ai_fetch', 'gptbot', null, '/docs', 1);
    S::event($this->site, '2026-03-02', 'ai_fetch', 'gptbot', null, '/', 1);
    S::event($this->site, '2026-03-01', 'ai_fetch', 'chatgpt-user', null, '/', 2);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'ai_referrals', $from, $to))->toBe([
        ['source' => 'chatgpt.com', 'visits' => 4, 'visitors' => 4, 'pageviews' => 6],
        ['source' => 'claude.ai', 'visits' => 1, 'visitors' => 1, 'pageviews' => 1],
    ])
        ->and($this->q->breakdown($this->site, 'ai_fetchers', $from, $to))->toBe([
            ['name' => 'gptbot', 'fetch_type' => 'crawler', 'count' => 11, 'pages' => 2],
            ['name' => 'chatgpt-user', 'fetch_type' => 'user', 'count' => 2, 'pages' => 1],
        ])
        ->and($this->q->breakdown($this->site, 'ai_fetched_pages', $from, $to, 1))->toBe([['name' => 'gptbot', 'path' => '/docs', 'count' => 10]])
        ->and($this->q->ai($this->site, $from, $to))->toBe([
            'visits' => 5, 'visitors' => 5, 'pageviews' => 7, 'fetches' => 13,
            'series' => [
                ['date' => '2026-03-01', 'visits' => 4, 'visitors' => 4, 'fetches' => 11],
                ['date' => '2026-03-02', 'visits' => 1, 'visitors' => 1, 'fetches' => 2],
            ],
        ]);
});

it('returns all-time public counts for the requested paths and events', function () {
    S::page($this->site, '2026-01-01', '/projects/a', ['pageviews' => 3]);
    S::page($this->site, '2026-03-02', '/projects/a', ['pageviews' => 4]);
    S::page($this->site, '2026-03-02', '/projects/b', ['pageviews' => 9]);
    S::page(S::site('Other'), '2026-03-02', '/projects/a', ['pageviews' => 100]);
    S::event($this->site, '2026-01-01', 'custom', 'like', null, '/blog/x', 2, 2, 'post-1');
    S::event($this->site, '2026-03-01', 'custom', 'like', null, '/blog/x', 1, 1, 'post-1');
    S::event($this->site, '2026-03-01', 'custom', 'share', null, '/blog/x', 5, 5, 'post-1');

    expect($this->q->publicCounts($this->site, ['/projects/a', '/missing'], ['like']))->toBe([
        'pages' => ['/projects/a' => ['pageviews' => 7, 'last_date' => '2026-03-02']],
        'events' => ['like' => ['post-1' => 3]],
    ])
        ->and($this->q->publicCounts($this->site, [], []))->toBe(['pages' => [], 'events' => []]);
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
    S::page($this->site, '2026-03-01', '/', ['pageviews' => 1, 'visitors' => 1, 'entries' => 1, 'exits' => 1, 'page_type' => 'home', 'content_group' => 'home', 'engaged_views' => 1, 'engaged_seconds' => 5]);
    S::source($this->site, '2026-03-01', 'utm', 'x', ['visits' => 1, 'visitors' => 1, 'pageviews' => 1, 'bounces' => 0], ['utm_medium' => 'm', 'utm_campaign' => 'c', 'utm_content' => null, 'utm_term' => null]);
    S::source($this->site, '2026-03-01', 'ai', 'chatgpt.com', ['visits' => 1, 'visitors' => 1, 'pageviews' => 1, 'bounces' => 0]);
    S::transition($this->site, '2026-03-01', '/', '/a', 1, 1);
    foreach (['utm' => 'x', 'search' => 'google.com', 'ai' => 'chatgpt.com'] as $type => $source) {
        S::entry($this->site, '2026-03-01', '/', $type, $source, 1, 1, 'c');
    }
    $this->site->update(['locales' => ['zh'], 'default_locale' => 'en']);
    foreach (['country', 'device', 'browser', 'os', 'language', 'locale'] as $dimension) {
        S::dimension($this->site, '2026-03-01', $dimension, 'v', 1, 1);
    }
    foreach (['outbound', 'download', 'custom', 'ai_fetch'] as $kind) {
        S::event($this->site, '2026-03-01', $kind, $kind, 't', '/', 1, 1);
    }

    $rows = $this->q->breakdown($this->site, $report, d('2026-03-01'), d('2026-03-01'));

    expect($rows)->not->toBeEmpty()
        ->and(array_keys($rows[0]))->toBe(StatsQuery::COLUMNS[$report]);
})->with(StatsQuery::REPORTS);

it('breaks ties by every grouped column, so offset pages neither repeat nor skip rows', function () {
    foreach (['zeta.com', 'alpha.com', 'mid.com'] as $source) {
        S::entry($this->site, '2026-03-01', '/same', 'search', $source, 1, 1);
        S::entry($this->site, '2026-03-01', '/same', 'referral', $source, 1, 1);
    }
    foreach (['https://z.example/', 'https://a.example/'] as $target) {
        S::event($this->site, '2026-03-01', 'custom', 'cta', $target, '/p', 1, 1, 'same');
    }
    [$from, $to] = [d('2026-03-01'), d('2026-03-01')];

    $paged = [];
    foreach ([0, 2, 4] as $offset) {
        $paged = [...$paged, ...$this->q->breakdown($this->site, 'landing_pages', $from, $to, 2, $offset)];
    }
    expect(array_map(fn ($r) => $r['source_type'].':'.$r['source'], $paged))->toBe([
        'referral:alpha.com', 'referral:mid.com', 'referral:zeta.com', 'search:alpha.com', 'search:mid.com', 'search:zeta.com',
    ])
        ->and(array_column($this->q->breakdown($this->site, 'search_pages', $from, $to), 'source'))->toBe(['alpha.com', 'mid.com', 'zeta.com'])
        ->and(array_column($this->q->breakdown($this->site, 'events', $from, $to), 'target'))->toBe(['https://a.example/', 'https://z.example/']);
});

it('reports events per site language version of the page they happened on', function () {
    $this->site->update(['locales' => ['cn', 'de'], 'default_locale' => 'en']);
    S::event($this->site, '2026-03-01', 'custom', 'cta.install', null, '/wtfaa', 5, 4, 'wtfaa');
    S::event($this->site, '2026-03-02', 'custom', 'cta.install', null, '/pricing', 1, 1, 'wtfaa');
    S::event($this->site, '2026-03-01', 'custom', 'cta.install', null, '/cn/wtfaa', 3, 3, 'wtfaa');
    S::event($this->site, '2026-03-01', 'outbound', '', 'https://github.com/x', '/de/wtfaa', 2, 2);
    S::event($this->site, '2026-03-01', 'ai_fetch', 'gptbot', null, '/cn/wtfaa', 9);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'event_locales', $from, $to))->toBe([
        ['locale' => 'en', 'kind' => 'custom', 'name' => 'cta.install', 'count' => 6, 'visitors' => 5],
        ['locale' => 'cn', 'kind' => 'custom', 'name' => 'cta.install', 'count' => 3, 'visitors' => 3],
        ['locale' => 'de', 'kind' => 'outbound', 'name' => '', 'count' => 2, 'visitors' => 2],
    ])
        ->and($this->q->breakdown($this->site, 'event_locales', $from, $to, 1, 1))->toBe([
            ['locale' => 'cn', 'kind' => 'custom', 'name' => 'cta.install', 'count' => 3, 'visitors' => 3],
        ])
        ->and($this->q->breakdown($this->site, 'event_locales', $from, $to, 50, 0, 3))->toHaveCount(2)
        ->and($this->q->total($this->site, 'event_locales', $from, $to))->toBe(11);

    $this->site->update(['locales' => null]);
    expect($this->q->breakdown($this->site, 'event_locales', $from, $to))->toBe([]);
});

it('reports time on page and scroll depth per page, and the average time on page', function () {
    S::page($this->site, '2026-03-01', '/guide', ['pageviews' => 6, 'engaged_views' => 4, 'engaged_seconds' => 400, 'time_10' => 4, 'time_30' => 3, 'time_60' => 2, 'time_180' => 1, 'scroll_25' => 4, 'scroll_50' => 3, 'scroll_75' => 2, 'scroll_100' => 1, 'title' => 'Guide']);
    S::page($this->site, '2026-03-02', '/guide', ['pageviews' => 1, 'engaged_views' => 1, 'engaged_seconds' => 5, 'scroll_25' => 1]);
    S::page($this->site, '2026-03-01', '/home', ['pageviews' => 9, 'engaged_views' => 0]);

    [$from, $to] = [d('2026-03-01'), d('2026-03-02')];

    expect($this->q->breakdown($this->site, 'engagement', $from, $to))->toBe([[
        'path' => '/guide', 'title' => 'Guide', 'engaged_views' => 5, 'avg_seconds' => 81,
        'time_10' => 0.8, 'time_30' => 0.6, 'time_60' => 0.4, 'time_180' => 0.2,
        'scroll_25' => 1.0, 'scroll_50' => 0.6, 'scroll_75' => 0.4, 'scroll_100' => 0.2,
    ]])
        ->and($this->q->summary($this->site, $from, $to)['avg_page_seconds'])->toBe(81)
        ->and($this->q->total($this->site, 'engagement', $from, $to))->toBe(5);
});

it('handles page rows that only carry engagement (a page view reported after midnight)', function () {
    // Opened on the 1st, reported on the 2nd: the 2nd has seconds and thresholds but no page view and no measured view.
    S::page($this->site, '2026-03-01', '/late', ['pageviews' => 1, 'engaged_views' => 1, 'engaged_seconds' => 20, 'time_10' => 1, 'scroll_25' => 1]);
    S::page($this->site, '2026-03-02', '/late', ['pageviews' => 0, 'engaged_views' => 0, 'engaged_seconds' => 40, 'time_30' => 1]);

    $day2 = d('2026-03-02');
    expect($this->q->summary($this->site, $day2, $day2)['avg_page_seconds'])->toBe(0)
        ->and($this->q->timeseries($this->site, $day2, $day2)[0]['avg_page_seconds'])->toBe(0)
        ->and($this->q->breakdown($this->site, 'pages', $day2, $day2))->toBe([])
        ->and($this->q->breakdown($this->site, 'engagement', d('2026-03-01'), $day2))->toBe([[
            'path' => '/late', 'title' => null, 'engaged_views' => 1, 'avg_seconds' => 60,
            'time_10' => 1.0, 'time_30' => 1.0, 'time_60' => 0.0, 'time_180' => 0.0,
            'scroll_25' => 1.0, 'scroll_50' => 0.0, 'scroll_75' => 0.0, 'scroll_100' => 0.0,
        ]]);
});

it('sums time on page and scroll depth into shares of the measured page views', function () {
    S::page($this->site, '2026-03-01', '/a', ['pageviews' => 9, 'engaged_views' => 6, 'time_10' => 3, 'time_30' => 2, 'scroll_25' => 6, 'scroll_100' => 1]);
    S::page($this->site, '2026-03-02', '/b', ['pageviews' => 5, 'engaged_views' => 2, 'time_10' => 1, 'scroll_25' => 2, 'scroll_50' => 1]);
    S::page($this->site, '2026-03-03', '/c', ['pageviews' => 50, 'engaged_views' => 50, 'time_10' => 50]); // outside

    expect($this->q->engagementTotals($this->site, d('2026-03-01'), d('2026-03-02')))->toBe([
        'views' => 8, 'time_10' => 0.5, 'time_30' => 0.25, 'time_60' => 0.0, 'time_180' => 0.0,
        'scroll_25' => 1.0, 'scroll_50' => 0.125, 'scroll_75' => 0.0, 'scroll_100' => 0.125,
    ])->and($this->q->engagementTotals($this->site, d('2026-04-01'), d('2026-04-01'))['views'])->toBe(0);
});

it('gives the daily visitors per source type in a fixed type order', function () {
    S::source($this->site, '2026-03-01', 'search', 'google.com', ['visits' => 2, 'visitors' => 2, 'pageviews' => 2]);
    S::source($this->site, '2026-03-01', 'search', 'bing.com', ['visits' => 1, 'visitors' => 1, 'pageviews' => 1]);
    S::source($this->site, '2026-03-02', 'direct', null, ['visits' => 4, 'visitors' => 3, 'pageviews' => 4]);
    S::source($this->site, '2026-03-02', 'utm', 'news', ['visits' => 0, 'visitors' => 5, 'pageviews' => 5]); // imported, no visits

    expect($this->q->sourceTypeSeries($this->site, d('2026-03-01'), d('2026-03-03')))->toBe([
        'dates' => ['2026-03-01', '2026-03-02', '2026-03-03'],
        'series' => ['utm' => [0, 5, 0], 'search' => [3, 0, 0], 'direct' => [0, 3, 0]],
    ]);
});

it('gives the daily count of the most frequent custom events', function () {
    S::event($this->site, '2026-03-01', 'custom', 'signup', null, '/a', 3);
    S::event($this->site, '2026-03-02', 'custom', 'signup', null, '/b', 2);
    S::event($this->site, '2026-03-02', 'custom', 'cta', null, '/a', 4);
    S::event($this->site, '2026-03-02', 'custom', 'rare', null, '/a', 1);
    S::event($this->site, '2026-03-02', 'outbound', 'outbound', 'https://x.example/', '/a', 9); // not a custom event

    expect($this->q->eventSeries($this->site, d('2026-03-01'), d('2026-03-02'), 2))->toBe([
        'dates' => ['2026-03-01', '2026-03-02'],
        'series' => ['signup' => [3, 2], 'cta' => [0, 4]],
    ]);
});

it('relates the custom events of a page to its page views', function () {
    S::page($this->site, '2026-03-01', '/pricing', ['pageviews' => 40]);
    S::page($this->site, '2026-03-02', '/pricing', ['pageviews' => 10]);
    S::page($this->site, '2026-03-01', '/', ['pageviews' => 100]);
    S::event($this->site, '2026-03-01', 'custom', 'cta.install', null, '/pricing', 5);
    S::event($this->site, '2026-03-02', 'custom', 'cta.pricing', null, '/pricing', 5);
    S::event($this->site, '2026-03-01', 'custom', 'cta.install', null, '/', 4);
    S::event($this->site, '2026-03-01', 'custom', 'cta.install', null, '/no-views', 7); // no page views: no rate
    S::event($this->site, '2026-03-01', 'outbound', 'outbound', 'https://x.example/', '/', 50);

    expect($this->q->eventRates($this->site, d('2026-03-01'), d('2026-03-02')))->toBe([
        ['path' => '/pricing', 'events' => 10, 'pageviews' => 50, 'rate' => 0.2],
        ['path' => '/', 'events' => 4, 'pageviews' => 100, 'rate' => 0.04],
    ]);
});
