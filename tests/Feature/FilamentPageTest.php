<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\Widgets\Report\AiChart;
use N2ns\Stats\Filament\Widgets\Report\AiKpis;
use N2ns\Stats\Filament\Widgets\Report\Kpis;
use N2ns\Stats\Filament\Widgets\Report\ReportChart;
use N2ns\Stats\Filament\Widgets\Report\ReportTable;
use N2ns\Stats\Filament\Widgets\Report\TrafficChart;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Reports\StatsQuery;
use N2ns\Stats\Tests\Feature\FilamentPanel;
use N2ns\Stats\Tests\StatsSeeder as S;
use N2ns\Stats\Tests\TestCase;

use function Livewire\invade;

// The host turned the standalone dashboard off: the panel must not need its routes.
beforeAll(fn () => TestCase::$config = ['stats.dashboard_enabled' => false]);
afterAll(fn () => TestCase::$config = []);

beforeEach(function () {
    FilamentPanel::register($this->app, fn ($plugin) => $plugin->navigationGroup('Reports')->navigationLabel('Analytics'));
    Carbon::setTestNow('2026-03-10 12:00:00');
});

it('refuses the statistics page and its widgets without the viewStats gate', function () {
    $site = S::site('Alpha');
    $this->get('/admin/stats')->assertForbidden();
    expect(StatsDashboard::canAccess())->toBeFalse();
    foreach ([Kpis::class, TrafficChart::class, AiKpis::class, AiChart::class, ReportChart::class, ReportTable::class] as $widget) {
        expect($widget::canView())->toBeFalse();
        Livewire::test($widget, ['pageFilters' => ['site' => $site->public_id]])->assertForbidden();
    }
});

it('renders the statistics page natively with KPIs, the chart and every report tab', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');
    S::totals($site, '2026-03-09', ['visitors' => 1234, 'visits' => 1500, 'pageviews' => 4500, 'bounces' => 300, 'events' => 4, 'outbound' => 2]);
    S::totals($site, '2026-03-02', ['visitors' => 1000, 'visits' => 1000, 'pageviews' => 4000, 'bounces' => 100, 'events' => 4, 'outbound' => 4]);
    S::source($site, '2026-03-09', 'search', 'google.com', ['visits' => 77, 'visitors' => 70, 'pageviews' => 90, 'bounces' => 7]);
    S::page($site, '2026-03-09', '/pricing', ['pageviews' => 321, 'visitors' => 200]);

    $response = $this->get('/admin/stats?filters[site]='.$site->public_id.'&filters[range]=7d')->assertOk();

    $response->assertSee('class="fi', false)
        ->assertSee('Reports')
        ->assertSee('Analytics')
        ->assertDontSee('stats-dash')                       // no embedded standalone dashboard
        ->assertSee('Compared: Mar 4–9 vs Feb 26 – Mar 3, complete days only.')
        ->assertSee('Today is still in progress')
        ->assertSeeInOrder(['Visitors', '1,234', '+23.4% vs previous period', 'Bounce rate', '20.0%', '+100.0% vs previous period', 'Outbound clicks', '2', 'Realtime'])
        ->assertSee('Daily traffic')
        ->assertSee('fi-wi-chart', false)
        ->assertSee('google.com')
        ->assertSee('/pricing')
        ->assertSee('100.0%')
        ->assertDontSee('/stats/sites/', false);
    foreach (array_keys(Dashboard::TABS) as $tab) {
        $response->assertSee(__('stats::filament.tabs.'.$tab));
    }
    foreach (StatsQuery::REPORTS as $report) {
        $response->assertSee(__('stats::reports.titles.'.$report));
    }
});

it('colours the KPI changes by whether they are better, with lower bounce rates better', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');
    S::totals($site, '2026-03-09', ['visitors' => 20, 'visits' => 20, 'pageviews' => 20, 'bounces' => 2]);
    S::totals($site, '2026-03-08', ['visitors' => 10, 'visits' => 10, 'pageviews' => 30, 'bounces' => 5]);

    $stats = collect(invade(Livewire::test(Kpis::class, ['pageFilters' => ['site' => $site->public_id, 'range' => 'today']])->instance())->getStats())
        ->keyBy(fn ($stat) => (string) $stat->getLabel());

    expect($stats['Visitors']->getColor())->toBe('success')
        ->and($stats['Visitors']->getChart())->toBe([0])                       // today only
        ->and($stats['Views per visit']->getColor())->toBe('danger')
        ->and($stats['Bounce rate']->getColor())->toBe('success')             // 10% after 50%
        ->and($stats['Events']->getColor())->toBe('gray')                     // nothing to compare with
        ->and($stats['Realtime']->getValue())->toBe('0');
});

it('gives the charts the daily series and the donut its top slices', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');
    S::totals($site, '2026-03-09', ['visitors' => 5, 'visits' => 6, 'pageviews' => 7, 'events' => 1, 'outbound' => 2]);
    S::source($site, '2026-03-09', 'ai', 'chatgpt.com', ['visits' => 3, 'visitors' => 3, 'pageviews' => 3, 'bounces' => 0]);
    S::event($site, '2026-03-08', 'ai_fetch', 'GPTBot', null, '/a', 4);
    foreach (['de' => 9, 'fr' => 5, 'us' => 4, 'jp' => 3, 'cn' => 2, 'br' => 1] as $country => $visits) {
        S::dimension($site, '2026-03-09', 'country', $country, $visits, $visits);
    }
    $filters = ['site' => $site->public_id, 'range' => '7d'];

    $traffic = invade(Livewire::test(TrafficChart::class, ['pageFilters' => $filters])->instance())->getData();
    expect($traffic['labels'])->toHaveCount(7)->and(end($traffic['labels']))->toBe('2026-03-10')
        ->and(array_column($traffic['datasets'], 'label'))->toBe(['Visitors', 'Visits', 'Pageviews', 'Events', 'Outbound clicks'])
        ->and($traffic['datasets'][2]['data'])->toBe([0, 0, 0, 0, 0, 7, 0]);

    $ai = invade(Livewire::test(AiChart::class, ['pageFilters' => $filters])->instance())->getData();
    expect(array_column($ai['datasets'], 'label'))->toBe(['AI visits', 'AI visitors', 'AI fetches'])
        ->and($ai['datasets'][0]['data'])->toBe([0, 0, 0, 0, 0, 3, 0])
        ->and($ai['datasets'][2]['data'])->toBe([0, 0, 0, 0, 4, 0, 0]);
    Livewire::test(AiKpis::class, ['pageFilters' => $filters])->assertSeeInOrder(['AI visits', '3', 'AI visitors', '3', 'AI fetches', '4']);

    $donut = invade(Livewire::test(ReportChart::class, ['report' => 'countries', 'pageFilters' => $filters])->instance())->getData();
    expect($donut['labels'])->toBe(['de', 'fr', 'us', 'jp', 'cn', 'Other'])
        ->and($donut['datasets'][0]['data'])->toBe([9, 5, 4, 3, 2, 1]);
    expect(invade(Livewire::test(ReportChart::class, ['report' => 'devices', 'pageFilters' => $filters])->instance())->getData())->toBe([]);
});

it('formats report tables, pages them by 25 and sorts and searches them', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');
    foreach (range(1, 30) as $i) {
        S::page($site, '2026-03-09', sprintf('/p%02d', $i), ['pageviews' => 100 + $i, 'visitors' => $i]);
    }
    S::page($site, '2026-03-09', '/pricing', ['pageviews' => 1, 'visitors' => 1, 'engaged_views' => 4, 'engaged_seconds' => 500, 'time_10' => 3]);
    $filters = ['site' => $site->public_id, 'range' => '7d'];

    $table = Livewire::test(ReportTable::class, ['report' => 'pages', 'pageFilters' => $filters])
        ->assertSee('Top pages')
        ->assertSeeInOrder(['/p30', '/p29', '/p06'])
        ->assertDontSee('/p05')
        ->assertDontSee('/pricing');
    $table->call('setPage', 2, 'pagesPage')->assertSee('/p05')->assertSee('/pricing')->assertDontSee('/p30');
    $table->call('setPage', 1, 'pagesPage')->call('sortTable', 'path', 'asc')->assertSeeInOrder(['/p01', '/p02'])->assertDontSee('/p30');
    $table->set('tableSearch', 'pric')->assertSee('/pricing')->assertDontSee('/p01');

    Livewire::test(ReportTable::class, ['report' => 'engagement', 'pageFilters' => $filters])
        ->assertSeeInOrder(['/pricing', '2m 05s', '75.0%', '0.0%', '100.0%']);
    S::source($site, '2026-03-09', 'search', 'google.com', ['visits' => 10, 'visitors' => 9, 'pageviews' => 12, 'bounces' => 4]);
    Livewire::test(ReportTable::class, ['report' => 'sources', 'pageFilters' => $filters])
        ->assertSeeInOrder(['Search', 'google.com', '10', '9', '12', '40.0%', '100.0%']);
    Livewire::test(ReportTable::class, ['report' => 'outbound', 'pageFilters' => $filters])->assertSee('No data for this range.');
});

it('exports a report as CSV with formulas neutralised', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');
    S::page($site, '2026-03-09', '/a', ['pageviews' => 5, 'visitors' => 3]);
    S::page($site, '2026-03-09', '/=cmd', ['pageviews' => 2, 'visitors' => 1, 'title' => '=HYPERLINK("x")']);

    Livewire::test(ReportTable::class, ['report' => 'pages', 'pageFilters' => ['site' => $site->public_id, 'range' => '7d']])
        ->callAction(TestAction::make('export')->table())
        ->assertFileDownloaded(
            'stats-'.$site->public_id.'-pages-2026-03-04-2026-03-10.csv',
            "path,title,page_type,pageviews,visitors,entries,exits\n/a,,,5,3,0,0\n/=cmd,\"'=HYPERLINK(\"\"x\"\")\",,2,1,0,0\n",
        );
});

it('switches sites and ranges and falls back to 30 days on an invalid custom range', function () {
    FilamentPanel::allowStats();
    $alpha = S::site('Alpha');
    $beta = S::site('Beta');
    S::totals($beta, '2026-01-15', ['visitors' => 4321, 'visits' => 4321, 'pageviews' => 4321]);

    $this->get('/admin/stats?filters[site]='.$beta->public_id.'&filters[range]=custom&filters[from]=2026-01-01&filters[to]=2026-01-31')
        ->assertOk()
        ->assertSee('4,321')
        ->assertSee('Compared: Jan 1–31 vs Dec 1, 2025 – Dec 31, 2025, complete days only.')
        ->assertDontSee('Today is still in progress');
    $this->get('/admin/stats?filters[site]=unknown&filters[range][]=x')
        ->assertOk()
        ->assertSee('Compared: Feb 9 – Mar 9 vs Jan 11 – Feb 8, complete days only.');

    Livewire::test(StatsDashboard::class)
        ->assertSet('filters.site', $alpha->public_id)
        ->assertSet('filters.range', '30d')
        ->set('filters.site', $beta->public_id)
        ->set('filters.range', 'custom')
        ->set('filters.from', '2026-01-31')
        ->set('filters.to', '2026-01-01')
        ->assertSee('Invalid custom range')
        ->assertSee('Compared: Feb 9 – Mar 9 vs Jan 11 – Feb 8, complete days only.')
        ->set('filters.to', '2026-02-01')
        ->assertDontSee('Invalid custom range')
        ->assertSee('Compared: Jan 31 – Feb 1 vs Jan 29–30, complete days only.');
});

it('offers to create a site when there are none', function () {
    FilamentPanel::allowStats();
    $this->get('/admin/stats')->assertOk()->assertSee('No sites yet.')->assertSee('/admin/stats-sites/create', false);
});

it('renders the statistics page in Simplified Chinese', function () {
    FilamentPanel::allowStats();
    app()->setLocale('zh_CN');
    $site = S::site('Alpha');
    S::page($site, '2026-03-09', '/a', ['pageviews' => 5, 'visitors' => 3]);

    $this->get('/admin/stats?filters[range]=15d')->assertOk()
        ->assertSee('最近 15 天')
        ->assertSee('热门页面')
        ->assertSee('每日流量')
        ->assertSee('较上一时段')
        ->assertSee('页面')
        ->assertDontSee('stats::');
});
