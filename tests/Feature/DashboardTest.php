<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Http\View\Dashboard;
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
    S::totals($site, $d, ['visitors' => 40, 'visits' => 50, 'pageviews' => 120, 'events' => 7, 'outbound' => 3]);
    S::page($site, $d, '/pricing', ['pageviews' => 300, 'visitors' => 200, 'entries' => 30, 'exits' => 20, 'title' => 'Pricing page', 'page_type' => 'landing', 'content_group' => 'Sales', 'entry_pageviews' => 60, 'entry_outbound' => 2, 'entry_events' => 4]);
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
    S::page($site, '2026-03-09', '/pricing', ['pageviews' => 321, 'visitors' => 200]);
    S::page($site, '2026-03-10', '/about', ['pageviews' => 3, 'visitors' => 3]);
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
        ->toContain('2026-03-09: 14 Events')
        ->toContain('2026-03-09: 15 Outbound clicks')
        ->toContain('<td>2026-03-09</td><td class="num">11</td><td class="num">12</td><td class="num">13</td><td class="num">14</td><td class="num">15</td>')
        ->not->toContain('cdn')
        ->not->toContain('<script src')
        ->and(substr_count($html, '<path class="s'))->toBe(5 + 3); // daily chart + AI chart
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
        ->assertSee('Site language versions')->assertSee('Events by page')->assertSee('AI fetchers')->assertSee('Pages fetched by AI')
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
        ->assertSee('<span style="width:75%"></span>', false);
});

it('shows the AI summary and the daily AI chart', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))
        ->assertOk()
        ->assertSeeInOrder(['AI visits', '10', 'AI visitors', '9', 'AI fetches', '8'])
        ->assertSee('Daily AI visits and fetches')
        ->assertSee('2026-03-09: 8 AI fetches');
});

it('draws accessible donuts with a text legend for the share reports', function () {
    allowStats();
    $site = S::site();
    seedEveryReport($site);

    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))->assertOk()->getContent();

    expect(substr_count($html, '<svg class="donut"'))->toBe(count(Dashboard::DONUTS))
        ->and($html)->toContain('aria-label="Source types by Visits: share of each item; the legend lists the values."')
        ->toContain('aria-label="Devices by Visits: share of each item; the legend lists the values."')
        ->toContain('<span class="name">Desktop</span> 75 (75.0%)')
        ->toContain('<span class="name">Mobile</span> 25 (25.0%)')
        ->toContain('<span class="name">Search</span> 30 (60.0%)');
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
        ->assertSee('<table aria-labelledby="report-pages" data-sortable>', false)
        ->assertSee('<td class="num" data-value="1234">1,234</td>', false)
        ->assertSee("querySelectorAll('.stats-dash table[data-sortable]')", false)
        ->assertSee('aria-sort', false)
        ->assertSee('"Sort by %s"', false);
});

it('shows "No data" for reports without rows', function () {
    allowStats();
    $site = S::site();
    $html = $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id]))->assertOk()->getContent();
    expect(substr_count($html, 'No data for this range.'))->toBe(count(StatsQuery::REPORTS));
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
