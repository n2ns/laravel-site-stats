<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Http\View\Charts;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Http\View\LineChart;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\StatsQuery;
use N2ns\Stats\Tests\StatsSeeder as S;

function allowStats(): void
{
    Gate::define('viewStats', fn ($user = null) => true);
}

/** One or more rows for every report on 2026-03-09. */
function seedEveryReport(Site $site): void
{
    $d = '2026-03-09';
    $site->update(['locales' => ['zh-cn'], 'default_locale' => 'en']);
    S::totals($site, $d, ['visitors' => 40, 'visits' => 50, 'pageviews' => 120, 'events' => 7, 'outbound' => 3]);
    S::page($site, $d, '/pricing', ['pageviews' => 300, 'visitors' => 200, 'entries' => 30, 'exits' => 20, 'title' => 'Pricing page', 'page_type' => 'landing', 'content_group' => 'Sales', 'entry_pageviews' => 60, 'entry_outbound' => 2, 'entry_events' => 4, 'engaged_views' => 20, 'engaged_seconds' => 1200, 'time_30' => 12, 'scroll_75' => 9]);
    S::page($site, $d, '/blog/post', ['pageviews' => 100, 'visitors' => 80, 'entries' => 10, 'exits' => 5, 'title' => 'A post', 'page_type' => 'article', 'content_group' => 'Blog']);
    S::transition($site, $d, '/pricing', '/signup', 12, 10);
    S::source($site, $d, 'search', 'google.com', ['visits' => 30, 'visitors' => 28, 'pageviews' => 60, 'bounces' => 3, 'outbound' => 1, 'events' => 2]);
    S::source($site, $d, 'utm', 'newsletter', ['visits' => 10, 'visitors' => 10, 'pageviews' => 20, 'bounces' => 1], ['utm_medium' => 'email', 'utm_campaign' => 'spring-sale']);
    S::source($site, $d, 'ai', 'chatgpt.com', ['visits' => 10, 'visitors' => 9, 'pageviews' => 15, 'bounces' => 0]);
    S::entry($site, $d, '/pricing', 'search', 'google.com', 30, 28);
    S::entry($site, $d, '/sale', 'utm', 'newsletter', 10, 10, 'spring-sale');
    S::entry($site, $d, '/answers', 'ai', 'chatgpt.com', 10, 9);
    foreach (['country' => 'DE', 'device' => 'mobile', 'browser' => 'Firefox', 'os' => 'Linux', 'language' => 'de-de', 'locale' => 'zh-cn'] as $dimension => $value) {
        S::dimension($site, $d, $dimension, $value, 25, 20, 60);
    }
    S::dimension($site, $d, 'device', 'desktop', 75, 60, 180);
    S::event($site, $d, 'outbound', 'outbound', 'https://partner.example/', '/pricing', 3, 3, 'Partner');
    S::event($site, $d, 'download', 'download', 'https://example.com/guide.pdf', '/pricing', 2, 2, 'Guide');
    S::event($site, $d, 'custom', 'signup', null, '/pricing', 5, 4, 'hero');
    S::event($site, $d, 'custom', 'signup', null, '/zh-cn/pricing', 2, 2, 'hero');
    S::event($site, $d, 'ai_fetch', 'gptbot', null, '/docs', 6);
    S::event($site, $d, 'ai_fetch', 'chatgpt-user', null, '/docs/api', 2);
}

beforeEach(function () {
    Carbon::setTestNow('2026-03-10 12:00:00');
});

it('denies every dashboard route by default', function (string $method, string $uri) {
    $site = S::site();
    $this->call($method, str_replace('{id}', $site->public_id, $uri))->assertForbidden();
})->with([
    ['GET', '/stats'],
    ['GET', '/stats/sites/{id}'],
    ['GET', '/stats/sites/{id}/export/pages'],
    ['GET', '/stats/manage/sites'],
    ['POST', '/stats/manage/sites'],
    ['PUT', '/stats/manage/sites/{id}'],
    ['POST', '/stats/manage/sites/{id}/key'],
]);

it('redirects to the site manager when there are no sites', function () {
    allowStats();
    $this->get('/stats')->assertRedirect(route('stats.sites.index'));
});

it('redirects to the first site, or the one picked in the switcher', function () {
    allowStats();
    $b = S::site('Beta');
    $a = S::site('Alpha');

    $this->get('/stats')->assertRedirect(route('stats.dashboard.site', ['publicId' => $a->public_id]));
    $this->get('/stats?site='.$b->public_id.'&range=7d')
        ->assertRedirect(route('stats.dashboard.site', ['publicId' => $b->public_id, 'range' => '7d']));
});

it('renders the KPIs with deltas over complete days, the comparison basis and the data caveat', function () {
    allowStats();
    $site = S::site('Example Site');
    S::totals($site, '2026-03-09', ['visitors' => 1234, 'visits' => 1500, 'pageviews' => 4500, 'bounces' => 600, 'duration_seconds' => 150000, 'events' => 40, 'outbound' => 12]);
    S::totals($site, '2026-03-10', ['visitors' => 10, 'visits' => 10, 'pageviews' => 30, 'events' => 2, 'outbound' => 1]); // today: in the totals, not in the deltas
    S::totals($site, '2026-03-02', ['visitors' => 617, 'visits' => 750, 'pageviews' => 900, 'events' => 20, 'outbound' => 6]);
    S::page($site, '2026-03-09', '/pricing', ['pageviews' => 321, 'visitors' => 200, 'exits' => 1500, 'entry_pageviews' => 4500]);
    S::page($site, '2026-03-10', '/about', ['pageviews' => 3, 'visitors' => 3, 'exits' => 10, 'entry_pageviews' => 30]);
    S::page($site, '2026-03-02', '/old', ['pageviews' => 0, 'exits' => 750, 'entry_pageviews' => 900]);
    S::event($site, '2026-03-09', 'custom', 'signup', null, '/', 3, 3, '<b>hero</b>');
    S::visit($site, 'v1', now()->subMinute());

    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))
        ->assertOk()
        ->assertSee('Example Site')
        ->assertSee('2026-03-04 – 2026-03-10', false)
        ->assertSee('Compared: Mar 4–9 vs Feb 26 – Mar 3, complete days only.')
        ->assertSee('Today is still in progress')
        ->assertSeeInOrder(['Visitors', '1,244', '+100.0%'])          // 1234 vs 617
        ->assertSeeInOrder(['Visits', '1,510', '+100.0%'])
        ->assertSeeInOrder(['Pageviews', '4,530', '+400.0%'])
        ->assertSeeInOrder(['Pages viewed', '2'])
        ->assertSeeInOrder(['Views per visit', '3.00', '+150.0%'])      // 3.00 vs 1.20
        ->assertSeeInOrder(['Bounce rate', '39.7%'])
        ->assertSeeInOrder(['Avg. visit duration', '1m 39s'])            // 150000 / 1510
        ->assertSeeInOrder(['Events', '42', '+100.0%'])
        ->assertSeeInOrder(['Outbound clicks', '13', '+100.0%'])
        ->assertSeeInOrder(['Realtime', '1', 'visits active in the last 5 min'])
        ->assertSee('&lt;b&gt;hero&lt;/b&gt;', false)
        ->assertDontSee('<b>hero</b>', false)
        ->assertSee('sets no cookies')
        ->assertSee('not recognised across days');
});

it('compares a past range with the period before it and shows no partial-day note', function () {
    allowStats();
    $site = S::site();
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'last_month']))
        ->assertOk()
        ->assertSee('Compared: Feb 1–28 vs Jan 4–31, complete days only.')
        ->assertDontSee('Today is still in progress');
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'today']))
        ->assertSee('Compared: Mar 9 vs Mar 8, complete days only.')
        ->assertSee('Today is still in progress');
});

it('shows the year in the comparison of a range in another year', function () {
    allowStats();
    $site = S::site();
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'custom', 'from' => '2025-12-01', 'to' => '2025-12-31']))
        ->assertSee('Compared: Dec 1, 2025 – Dec 31, 2025 vs Oct 31, 2025 – Nov 30, 2025, complete days only.');
});

it('charts and tabulates visitors, visits, pageviews, events and outbound clicks per day', function () {
    allowStats();
    $site = S::site();
    S::totals($site, '2026-03-09', ['visitors' => 11, 'visits' => 12, 'pageviews' => 13, 'events' => 14, 'outbound' => 15]);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();

    expect($html)->toContain('<svg viewBox="0 0 800 240" role="img"')
        ->toContain('<td>2026-03-09</td><td class="num">11</td><td class="num">12</td><td class="num">13</td><td class="num">14</td><td class="num">15</td>')
        ->not->toContain('cdn')
        ->not->toContain('<script src')
        ->not->toContain('<link')
        ->not->toContain('@import')
        ->not->toContain('url(')
        ->and(substr_count($html, '<path class="line"'))->toBe(5 + 3) // one line per metric: daily chart + AI chart
        ->and(substr_count($html, '<path class="area"'))->toBe(5 + 3);

});

it('covers every report in the tabs', function () {
    expect(array_merge(...array_values(Dashboard::TABS)))->toEqualCanonicalizing(StatsQuery::REPORTS);
});

it('renders every report with its title, rows, shares and CSV link', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $response = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk();

    foreach (StatsQuery::REPORTS as $report) {
        $response->assertSee('id="report-'.$report.'"', false)
            ->assertSee(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => $report, 'range' => '7d']), false);
    }
    $response->assertDontSee('No data for this range.')
        ->assertSeeInOrder(['Pages', 'Sources', 'Visitors', 'Events', 'AI'])
        ->assertSee('Top pages')->assertSee('Page transitions')->assertSee('Content groups')->assertSee('Landing pages by source')
        ->assertSee('Site language versions')->assertSee('Events by page')->assertSee('Events by site language version')->assertSee('AI fetchers')->assertSee('Pages fetched by AI')
        ->assertSee('Pricing page')
        ->assertSee('/signup')
        ->assertSee('spring-sale')
        ->assertSee('Campaign (UTM)')        // translated source type
        ->assertSee('User-triggered')        // chatgpt-user is a user-triggered fetcher
        ->assertSee('Crawler')
        ->assertSee('Outbound click')        // event kind
        ->assertSee('https://example.com/guide.pdf')
        ->assertSee('zh-cn')
        // pages: 300 and 100 pageviews
        ->assertSeeInOrder(['/pricing', 'data-value="0.75"', '75.0%', '/blog/post', 'data-value="0.25"', '25.0%'], false)
        ->assertSee('<span class="fill" style="width:75%" aria-hidden="true"></span>', false)
        ->assertSee('<span class="sub">Pricing page · landing</span>', false); // the other text columns under the label
});

it('shows the AI summary and the daily AI chart', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))
        ->assertOk()
        ->assertSeeInOrder(['AI visits', '10', 'AI visitors', '9', 'AI fetches', '8'])
        ->assertSee('Daily AI visits and fetches')
        ->assertSee('id="ai-chart-metric-fetches"', false)
        ->assertSee('<td>2026-03-09</td><td class="num">10</td><td class="num">9</td><td class="num">8</td>', false);
});

it('draws accessible donuts with a text legend for the share reports', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();

    expect(substr_count($html, '<svg class="donut"'))->toBe(count(Dashboard::DONUTS))
        ->and($html)->toContain('aria-label="Source types by Visitors: share of each item; the legend lists the values."')
        ->toContain('aria-label="Devices by Visitors: share of each item; the legend lists the values."')
        ->toContain('<span class="name">Desktop</span> 60 (75.0%)')
        ->toContain('<span class="name">Mobile</span> 20 (25.0%)')
        ->toContain('<span class="name">Search</span> 28 (59.6%)');
});

it('shows a rate without its base as a dash', function () {
    expect(Dashboard::cell('sources', 'bounce_rate', null))->toBe('–')
        ->and(Dashboard::cell('sources', 'bounce_rate', 0.25))->toBe('25.0%')
        ->and(Dashboard::cell('sources', 'source', null))->toBe('–');
});

it('puts the rest of the total into "Other" in a donut', function () {
    $rows = [];
    foreach (range(1, 7) as $i) {
        $rows[] = ['value' => "C{$i}", 'visits' => 10, 'visitors' => 10, 'pageviews' => 10];
    }
    $slices = Dashboard::donut('countries', $rows, 70);

    expect($slices)->toHaveCount(6)
        ->and($slices[5])->toMatchArray(['label' => 'Other', 'value' => 20])
        ->and(round($slices[5]['offset'], 4))->toBe(round(50 / 70, 4))
        ->and(Dashboard::donut('countries', [], 0))->toBe([]);
});

it('marks report tables as sortable with numeric sort values and ships the sorter', function () {
    allowStats();
    $site = S::site();
    S::page($site, '2026-03-09', '/a', ['pageviews' => 1234, 'visitors' => 5]);

    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))
        ->assertOk()
        ->assertSee('<table class="compact" aria-labelledby="report-pages" data-sortable data-top="10">', false)
        ->assertSee('<table aria-label="Top pages, all columns" data-sortable>', false)
        ->assertSee('<td class="num" data-value="1234">1,234</td>', false)
        ->assertSee("each('table[data-sortable]'", false)
        ->assertSee('aria-sort', false)
        ->assertSee('"Sort by %s"', false);
});

it('deals the cards of a tab out to a left and a right column after the wide charts', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();
    preg_match('/<div role="tabpanel" class="tabpanel" id="panel-pages".*?(?=<div role="tabpanel"|<\/section>)/s', $html, $panel);
    preg_match_all('/<div class="column">(.*?)<\/div>\n<\/div>\n(?=<div class="column">|<\/div>)/s', $panel[0], $columns);
    $ids = fn (string $html): array => (preg_match_all('/<article class="card[^"]*" id="(?:chart|card)-([a-z_]+)"/', $html, $m) ? $m[1] : []);
    $orders = fn (string $html): array => (preg_match_all('/<div class="cell" style="order:(\d+)">/', $html, $m) ? array_map('intval', $m[1]) : []);

    // page_flow is wide: above the columns; then time_on_page, scroll_depth, content_groups and the reports alternate.
    expect(strpos($panel[0], 'id="chart-page_flow"'))->toBeLessThan(strpos($panel[0], '<div class="columns">'))
        ->and($columns[1])->toHaveCount(2)
        ->and($ids($columns[1][0]))->toBe(['time_on_page', 'content_groups', 'entries', 'engagement', 'content'])
        ->and($ids($columns[1][1]))->toBe(['scroll_depth', 'pages', 'exits', 'page_types', 'transitions'])
        ->and($orders($columns[1][0]))->toBe([0, 2, 4, 6, 8])
        ->and($orders($columns[1][1]))->toBe([1, 3, 5, 7, 9])
        ->and($html)->toContain('.stats-dash .column{display:contents}'); // one column on narrow screens, in that order
});

it('draws the charts of every report tab', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();

    foreach (array_merge(...array_values(Charts::TABS)) as $chart) {
        expect($html)->toContain('id="chart-'.$chart.'"')->toContain(e(__('stats::dashboard.charts.'.$chart.'.title')));
    }
    expect($html)
        ->toContain('Of 20 measured page views, the share that stayed at least this long.')
        ->toContain('<div class="hbar-value">60.0%</div>')                                  // 12 of 20 stayed 30 s
        ->toContain('<span class="lbl" title="Pricing page">Pricing page</span><span class="sub">Sales</span>')
        ->toContain('<title>/pricing → /signup: 12</title>')
        ->toContain('<span class="name">Search</span> 28')                                 // visitors by source type
        ->toContain('<span class="lbl" title="Search · google.com">Search · google.com</span><span class="sub">30 visits</span>')
        ->toContain('<title>DE: 20 visitors</title>')
        ->toContain('<span class="name">signup</span> 7')                                  // daily count of the top events
        ->toContain('<span class="lbl" title="/pricing">/pricing</span><span class="sub">5 events / 300 page views</span>')
        ->toContain('<span class="lbl" title="gptbot">gptbot</span>')
        ->toContain('<path class="prev"')                                                 // the previous period on the daily chart
        ->toContain('Dashed: previous period')
        ->toContain('aria-label="Browsers by Visitors')
        ->toContain('aria-label="Operating systems by Visitors');
});

it('shows "No data" for reports and charts without rows', function () {
    allowStats();
    $site = S::site();
    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id]))->assertOk()->getContent();
    expect(substr_count($html, 'No data for this range.'))->toBe(count(StatsQuery::REPORTS) + count(array_merge(...array_values(Charts::TABS))));
});

it('supports every preset range and custom ranges', function (array $query, string $shown) {
    allowStats();
    $site = S::site();
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id] + $query))
        ->assertOk()
        ->assertSee($shown, false);
})->with([
    'today' => [['range' => 'today'], '2026-03-10 – 2026-03-10'],
    'yesterday' => [['range' => 'yesterday'], '2026-03-09 – 2026-03-09'],
    '7d' => [['range' => '7d'], '2026-03-04 – 2026-03-10'],
    '15d' => [['range' => '15d'], '2026-02-24 – 2026-03-10'],
    '30d' => [['range' => '30d'], '2026-02-09 – 2026-03-10'],
    '90d' => [['range' => '90d'], '2025-12-11 – 2026-03-10'],
    'month' => [['range' => 'month'], '2026-03-01 – 2026-03-10'],
    'last month' => [['range' => 'last_month'], '2026-02-01 – 2026-02-28'],
    'default' => [[], '2026-02-09 – 2026-03-10'],
    'custom' => [['range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31'], '2026-01-01 – 2026-01-31'],
]);

it('offers the last 15 days in the range selector', function () {
    allowStats();
    $site = S::site();
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '15d']))
        ->assertSee('<option value="15d" selected>Last 15 days</option>', false)
        ->assertSee('Compared: Feb 24 – Mar 9 vs Feb 10–23, complete days only.');
});

it('falls back to 30 days with a message on an invalid custom range', function (array $query) {
    allowStats();
    $site = S::site();
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'custom'] + $query))
        ->assertOk()
        ->assertSee('The custom range is invalid')
        ->assertSee('Showing the last 30 days instead')
        ->assertSee('2026-02-09 – 2026-03-10', false);
})->with([
    'too long' => [['from' => '2025-01-01', 'to' => '2026-03-01']],
    'reversed' => [['from' => '2026-03-02', 'to' => '2026-03-01']],
    'not a date' => [['from' => 'x', 'to' => '2026-03-01']],
]);

it('uses the site timezone for today', function () {
    allowStats();
    Carbon::setTestNow('2026-03-10 02:00:00');
    $site = S::site('NY', ['timezone' => 'America/New_York']);
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'today']))
        ->assertSee('2026-03-09 – 2026-03-09 (America/New_York)', false);
});

it('returns 404 for an unknown site', function () {
    allowStats();
    $this->get('/stats/sites/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
});

it('exports a report as CSV and neutralises formulas', function () {
    allowStats();
    $site = S::site();
    S::event($site, '2026-03-10', 'custom', 'signup', null, '/', 5, 4, '=HYPERLINK("x")');
    S::event($site, '2026-03-10', 'custom', 'cta', null, '/', 2, 2, 'hero, top');

    $response = $this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'events', 'range' => 'today']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="stats-'.$site->public_id.'-events-2026-03-10-2026-03-10.csv"');
    expect($response->getContent())->toBe(
        "name,label,target,count,visitors\n"
        ."signup,\"'=HYPERLINK(\"\"x\"\")\",,5,4\n"
        ."cta,\"hero, top\",,2,2\n"
    );
});

it('exports the new reports as CSV', function (string $report, string $expected) {
    allowStats();
    $site = S::site();
    seedEveryReport($site);
    S::event($site, '2026-03-09', 'custom', 'cta', null, '/x', 1, 1, '@cmd');

    $csv = $this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => $report, 'range' => '7d']))
        ->assertOk()->getContent();

    expect($csv)->toStartWith(implode(',', StatsQuery::COLUMNS[$report])."\n")->toContain($expected);
})->with([
    'transitions' => ['transitions', "/pricing,/signup,12,10\n"],
    'landing pages' => ['landing_pages', "utm,newsletter,spring-sale,/sale,10,10\n"],
    'page types' => ['page_types', "landing,300,200,1\n"],
    'locales' => ['locales', "zh-cn,25,20,60\n"],
    'event pages' => ['event_pages', "custom,cta,'@cmd,,/x,1,1\n"],
    'ai fetchers' => ['ai_fetchers', "gptbot,crawler,6,1\n"],
    'ai fetched pages' => ['ai_fetched_pages', "gptbot,/docs,6\n"],
]);

it('exports up to the export limit', function () {
    allowStats();
    $site = S::site();
    foreach (range(1, 501) as $i) {
        S::page($site, '2026-03-09', "/p{$i}", ['pageviews' => $i, 'visitors' => 1]);
    }
    $csv = $this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'pages', 'range' => '7d']))->getContent();
    expect(substr_count($csv, "\n"))->toBe(1 + 500);
});

it('exports an empty CSV for a range without data and 404 for unknown reports', function () {
    allowStats();
    $site = S::site();
    expect($this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'pages']))->assertOk()->getContent())
        ->toBe(implode(',', StatsQuery::COLUMNS['pages'])."\n");
    $this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'ai']))->assertNotFound();
    $this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'nope']))->assertNotFound();
});

it('ignores malformed query parameters', function () {
    allowStats();
    $site = S::site();
    $this->get('/stats?site[]=x')->assertRedirect(route('stats.dashboard.site', ['publicId' => $site->public_id]));
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id]).'?range[]=x&from[]=y')
        ->assertOk()->assertSee('2026-02-09 – 2026-03-10', false);
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id]).'?range=custom&from[]=y&to=2026-03-01')
        ->assertOk()->assertSee('Showing the last 30 days instead');
});

it('offers a metric selector for the daily chart with the data for its tooltip', function () {
    allowStats();
    $site = S::site();
    S::totals($site, '2026-03-09', ['visitors' => 11, 'visits' => 12, 'pageviews' => 130, 'events' => 14, 'outbound' => 15]);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();

    expect($html)->toContain('<input type="radio" class="metric-radio m0" name="chart-metric" id="chart-metric-visitors" value="visitors" aria-describedby="chart-metric-hint" checked>')
        ->toContain('id="chart-metric-outbound" value="outbound"')
        ->toContain('<label for="chart-metric-pageviews" class="t2">Pageviews</label>')
        ->toContain('<g class="layer l4" data-metric="outbound">')
        ->toContain('<div class="plot" tabindex="0" role="group" aria-label="Daily traffic: use the left and right arrow keys')
        ->toContain('<div class="tip" role="status" aria-live="polite" hidden></div>')
        ->and(substr_count($html, 'class="metric-radio'))->toBe(count(Dashboard::CHART_METRICS) + count(Dashboard::AI_CHART_METRICS));

    preg_match('/<section class="card chart" aria-labelledby="chart-title" data-chart="([^"]+)"/', $html, $m);
    $data = json_decode(html_entity_decode($m[1]), true);
    expect($data['dates'])->toHaveCount(7)
        ->and($data['dates'][5])->toBe('Mon, Mar 9')
        ->and(array_keys($data['metrics']))->toBe(Dashboard::CHART_METRICS)
        ->and($data['metrics']['pageviews'])->toBe(['label' => 'Pageviews', 'values' => [0, 0, 0, 0, 0, 130, 0], 'top' => 200])
        ->and($data['metrics']['visitors']['top'])->toBe(20); // 4 ticks of 5
});

it('builds one scale per chart metric with readable ticks and date labels', function () {
    $series = [];
    foreach (range(1, 30) as $day) {
        $series[] = ['date' => sprintf('2026-04-%02d', $day), 'visitors' => $day === 30 ? 1234 : 7, 'events' => 0];
    }
    $chart = LineChart::build($series, ['visitors', 'events']);

    expect($chart['layers']['visitors']['top'])->toBe(2000)
        ->and(array_column($chart['layers']['visitors']['ticks'], 'value'))->toBe([0, 500, 1000, 1500, 2000])
        ->and(array_column($chart['layers']['visitors']['ticks'], 'pct'))->toBe([100.0, 75.0, 50.0, 25.0, 0.0])
        ->and($chart['layers']['events']['top'])->toBe(4)
        ->and($chart['layers']['visitors']['line'])->toStartWith('M0 239.2 L')->toEndWith(' L800 91.9')
        ->and($chart['layers']['visitors']['area'])->toEndWith(' L800 240 L0 240 Z')
        ->and(array_column($chart['labels'], 'date'))->toBe(['2026-04-05', '2026-04-10', '2026-04-15', '2026-04-20', '2026-04-25', '2026-04-30'])
        ->and(array_column($chart['labels'], 'anchor'))->toBe(['middle', 'middle', 'middle', 'middle', 'middle', 'end']);

    $single = LineChart::build([['date' => '2026-04-01', 'visitors' => 3]], ['visitors']);
    expect($single['layers']['visitors']['line'])->toBe('M0 60 L800 60')
        ->and($single['labels'][0])->toMatchArray(['pct' => 50.0, 'anchor' => 'middle']);
});

it('shows the top 10 rows of a report card with a toggle to show all of them', function () {
    allowStats();
    $site = S::site();
    foreach (range(1, 12) as $i) {
        S::page($site, '2026-03-09', "/p{$i}", ['pageviews' => 100 - $i, 'visitors' => 1]);
    }
    S::page($site, '2026-03-09', '/blog', ['pageviews' => 5, 'visitors' => 5, 'exits' => 5]);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();
    preg_match('/<article class="card report" id="card-pages">.*?<\/article>/s', $html, $pages);
    preg_match('/<article class="card report" id="card-exits">.*?<\/article>/s', $html, $exits);

    expect($pages[0])->toContain('<button type="button" class="link more-toggle" aria-expanded="false" data-more="Show all 13" data-less="Show top 10" hidden>Show all 13</button>')
        ->and(substr_count($pages[0], '<tr class="more">'))->toBe(3)
        ->and(substr_count($pages[0], '<td class="key"><div class="bar-row">'))->toBe(13)
        ->and($exits[0])->not->toContain('more-toggle')
        ->and($html)->toContain('.stats-dash.js table.compact tr.more{display:none}')
        ->toContain("button.getAttribute(open ? 'data-less' : 'data-more')");
});

it('puts every column of wide reports into a details view', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();
    preg_match('/<article class="card report" id="card-engagement">.*?<\/article>/s', $html, $card);

    expect($card[0])->toContain('<details class="details">')
        ->toContain('<summary>All columns</summary>')
        ->toContain('<table aria-label="Time on page and scroll depth, all columns" data-sortable>')
        ->toContain('<th scope="col" class="num">≥ 10 s</th>')
        ->toContain('<th scope="col" class="num">Scrolled to end</th>')
        ->toContain('<td class="num" data-value="60">1m 00s</td>')              // 1200 s over 20 measured views
        ->toContain('<td class="num" data-value="0.6">60.0%</td>')               // 12 of 20 views stayed 30 s
        ->and(substr_count($card[0], '<th scope="col"'))->toBe(3 + count(StatsQuery::COLUMNS['engagement']) + 1)
        ->and($card[0])->toContain('<div class="table-scroll">');                // scrolls sideways inside its column

    expect(Dashboard::layout('engagement'))->toBe(['label' => 'path', 'sub' => ['title'], 'metric' => 'engaged_views', 'details' => true])
        ->and(Dashboard::layout('exits')['details'])->toBeFalse()
        ->and(Dashboard::layout('ai_fetched_pages'))->toBe(['label' => 'name', 'sub' => ['path'], 'metric' => 'count', 'details' => false])
        ->and(Dashboard::layout('countries'))->toBe(['label' => 'value', 'sub' => [], 'metric' => 'visitors', 'details' => true]);
});

it('splits the KPIs into primary and secondary metrics with tones and sparklines', function () {
    $totals = fn (array $values) => $values + ['visitors' => 0, 'visits' => 0, 'pageviews' => 0, 'pages' => 0, 'views_per_visit' => 0, 'bounce_rate' => 0, 'avg_duration_seconds' => 0, 'avg_page_seconds' => 0, 'events' => 0, 'outbound' => 0];
    $summary = $totals(['visitors' => 30, 'bounce_rate' => 0.25, 'avg_duration_seconds' => 125]) + ['comparison' => [
        'current' => $totals(['visitors' => 30, 'bounce_rate' => 0.25, 'events' => 4]),
        'previous' => $totals(['visitors' => 20, 'bounce_rate' => 0.5, 'events' => 8]),
    ]];
    $series = [
        ['date' => '2026-03-08', 'visitors' => 10, 'visits' => 10, 'pageviews' => 20, 'events' => 2, 'outbound' => 0, 'pages' => 3, 'views_per_visit' => 2.0, 'bounce_rate' => 0.5, 'avg_duration_seconds' => 60, 'avg_page_seconds' => 20],
        ['date' => '2026-03-09', 'visitors' => 20, 'visits' => 10, 'pageviews' => 30, 'events' => 2, 'outbound' => 0, 'pages' => 3, 'views_per_visit' => 3.0, 'bounce_rate' => 0.25, 'avg_duration_seconds' => 90, 'avg_page_seconds' => 40],
    ];
    $kpis = array_column(Dashboard::kpis($summary, $series), null, 'key');

    expect(array_keys(array_filter($kpis, fn ($k) => $k['primary'])))->toBe(['visitors', 'visits', 'pageviews', 'bounce_rate', 'avg_duration_seconds'])
        ->and($kpis['visitors'])->toMatchArray(['value' => '30', 'delta' => '+50.0%', 'direction' => 'up', 'tone' => 'good', 'spark' => 'M0 28 L100 2'])
        ->and($kpis['bounce_rate'])->toMatchArray(['value' => '25.0%', 'delta' => '−50.0%', 'direction' => 'down', 'tone' => 'good', 'spark' => 'M0 2 L100 28']) // lower is better
        ->and($kpis['events'])->toMatchArray(['direction' => 'down', 'tone' => 'bad', 'spark' => 'M0 15 L100 15'])  // a flat series sits in the middle
        ->and($kpis['avg_duration_seconds'])->toMatchArray(['value' => '2m 05s', 'delta' => '–', 'tone' => 'flat'])
        ->and($kpis['views_per_visit']['spark'])->toBe('M0 28 L100 2')                                       // 2.0 then 3.0 views per visit
        ->and(LineChart::sparkline([5]))->toBeNull();
});

it('renders the KPI strip with arrows, the comparison label and sparklines', function () {
    allowStats();
    $site = S::site();
    S::totals($site, '2026-03-09', ['visitors' => 20, 'visits' => 20, 'pageviews' => 40, 'bounces' => 5]);
    S::totals($site, '2026-03-02', ['visitors' => 10, 'visits' => 10, 'pageviews' => 40, 'bounces' => 5]);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();

    expect($html)->toContain('<div class="kpis primary"')
        ->toContain('<div class="kpis secondary" role="group" aria-label="More metrics">')
        ->toContain('<div class="delta good" title="Compared days: 20; previous days: 10"><span aria-hidden="true">↑</span> +100.0%')
        ->toContain('<div class="delta good" title="Compared days: 25.0%; previous days: 50.0%"><span aria-hidden="true">↓</span> −50.0%') // lower bounce rate is better
        ->toContain('vs previous days')
        ->toContain('<svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"')
        ->and(substr_count($html, '<svg class="spark"'))->toBe(10); // every KPI except realtime
});

it('ships light and dark design tokens, focus styles and reduced motion', function () {
    allowStats();
    $site = S::site();

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id]))->assertOk()->getContent();

    expect($html)->toContain('<meta name="color-scheme" content="light dark">')
        ->toContain('<body class="stats-dash">')
        ->toContain('.stats-dash{')
        ->toContain('--bg:#fafafa;--surface:#ffffff;')                // Filament's light zinc
        ->toContain('@media (prefers-color-scheme:dark){.stats-dash:not(.stats-filament):not(.stats-embedded){')
        ->toContain('--bg:#09090b;--surface:#18181b;')                // and dark zinc
        ->toContain('--accent:var(--primary-600,#d97706)')            // inside a panel: its primary colour
        ->toContain('font-variant-numeric:tabular-nums')
        ->toContain('.stats-dash :focus-visible{outline:2px solid var(--accent)')
        ->toContain('@media (prefers-reduced-motion:reduce)')
        ->and(substr_count($html, '--text:'))->toBe(3)   // light, dark media query, Filament .dark
        ->and(substr_count($html, '--accent:'))->toBe(4); // and the panel's primary colour inside Filament
});
