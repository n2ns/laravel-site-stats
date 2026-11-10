<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Tests\StatsSeeder as S;

function allowStats(): void
{
    Gate::define('viewStats', fn ($user = null) => true);
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

it('renders the dashboard with KPIs, comparison, chart, reports and the data caveat', function () {
    allowStats();
    $site = S::site('Example Site');
    S::totals($site, '2026-03-10', ['visitors' => 1234, 'visits' => 1500, 'pageviews' => 4500, 'bounces' => 600, 'duration_seconds' => 150000]);
    S::totals($site, '2026-03-03', ['visitors' => 617, 'visits' => 700, 'pageviews' => 900]); // previous 7 days
    S::page($site, '2026-03-10', '/pricing', ['pageviews' => 321, 'visitors' => 200, 'entries' => 10, 'exits' => 5]);
    S::source($site, '2026-03-10', 'search', 'google.com', ['visits' => 77, 'visitors' => 70, 'pageviews' => 90, 'bounces' => 7]);
    S::dimension($site, '2026-03-10', 'country', 'DE', 9, 9);
    S::event($site, '2026-03-10', 'ai_fetch', 'gptbot', null, '/docs', 3);
    S::event($site, '2026-03-10', 'custom', 'signup', '<b>hero</b>', '/', 3, 3);
    S::visit($site, 'v1', now()->subMinute());

    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))
        ->assertOk()
        ->assertSee('Example Site')
        ->assertSee('2026-03-04 – 2026-03-10', false)
        ->assertSee('2026-02-25 – 2026-03-03', false)
        ->assertSee('1,234')            // visitors
        ->assertSee('+100.0%')          // vs 617
        ->assertSee('3.00')             // views per visit
        ->assertSee('40.0%')            // bounce rate
        ->assertSee('1m 40s')           // 150000 / 1500
        ->assertSee('/pricing')
        ->assertSee('google.com')
        ->assertSee('DE')
        ->assertSee('gptbot')
        ->assertSee('&lt;b&gt;hero&lt;/b&gt;', false)
        ->assertDontSee('<b>hero</b>', false)
        ->assertSee('<svg', false)
        ->assertSee('Realtime')
        ->assertSee('sets no cookies')
        ->assertSee('not recognised across days')
        ->assertSee(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'pages', 'range' => '7d']), false);
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
    '30d' => [['range' => '30d'], '2026-02-09 – 2026-03-10'],
    '90d' => [['range' => '90d'], '2025-12-11 – 2026-03-10'],
    'month' => [['range' => 'month'], '2026-03-01 – 2026-03-10'],
    'last month' => [['range' => 'last_month'], '2026-02-01 – 2026-02-28'],
    'default' => [[], '2026-02-09 – 2026-03-10'],
    'custom' => [['range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31'], '2026-01-01 – 2026-01-31'],
]);

it('falls back to 30 days with a message on an invalid custom range', function (array $query) {
    allowStats();
    $site = S::site();
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'custom'] + $query))
        ->assertOk()
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
    S::event($site, '2026-03-10', 'custom', 'signup', '=HYPERLINK("x")', '/', 5, 4);
    S::event($site, '2026-03-10', 'custom', 'cta', 'hero, top', '/', 2, 2);

    $response = $this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'events', 'range' => 'today']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="stats-'.$site->public_id.'-events-2026-03-10-2026-03-10.csv"');
    expect($response->getContent())->toBe(
        "name,label,count,visitors\n"
        ."signup,\"'=HYPERLINK(\"\"x\"\")\",5,4\n"
        ."cta,\"hero, top\",2,2\n"
    );
});

it('exports an empty CSV for a range without data and 404 for unknown reports', function () {
    allowStats();
    $site = S::site();
    expect($this->get(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'pages']))->assertOk()->getContent())->toBe("path,pageviews,visitors,entries,exits\n");
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
