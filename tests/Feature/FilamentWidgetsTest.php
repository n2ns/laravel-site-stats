<?php

use Filament\Panel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;
use N2ns\Stats\Filament\StatsPlugin;
use N2ns\Stats\Filament\Widgets\Report\ReportTable;
use N2ns\Stats\Filament\Widgets\StatsOverview;
use N2ns\Stats\Filament\Widgets\TopSources;
use N2ns\Stats\Tests\Feature\FilamentPanel;
use N2ns\Stats\Tests\StatsSeeder as S;
use N2ns\Stats\Tests\TestCase;

// The host turned the standalone dashboard off: the widgets must not need its routes.
beforeAll(fn () => TestCase::$config = ['stats.dashboard_enabled' => false]);
afterAll(fn () => TestCase::$config = []);

beforeEach(function () {
    FilamentPanel::register($this->app);
    Carbon::setTestNow('2026-03-10 12:00:00');
});

it('registers the widgets, the statistics page and the Sites resource on a panel', function () {
    $panel = Panel::make()->id('other');
    $plugin = StatsPlugin::make();
    $plugin->register($panel);

    expect($plugin->getId())->toBe('n2ns-stats')
        ->and($panel->getWidgets())->toContain(StatsOverview::class, TopSources::class)
        ->and($panel->getPages())->toContain(StatsDashboard::class)
        ->and($panel->getResources())->toContain(SiteResource::class)
        ->and($panel->getWidgets())->not->toContain(ReportTable::class);
});

it('can leave the Sites resource out', function () {
    $panel = Panel::make()->id('other');
    $plugin = StatsPlugin::make();
    expect($plugin->hasSitesResource())->toBeTrue()
        ->and($plugin->sitesResource(false)->hasSitesResource())->toBeFalse();
    $plugin->register($panel);

    expect($panel->getResources())->not->toContain(SiteResource::class)
        ->and($panel->getPages())->toContain(StatsDashboard::class);
});

it('lets the host set the navigation group and label', function () {
    $plugin = StatsPlugin::make();
    expect($plugin->getNavigationGroup())->toBeNull()
        ->and($plugin->getNavigationLabel())->toBe('Statistics');

    $plugin->navigationGroup('Reports')->navigationLabel('Analytics');
    expect($plugin->getNavigationGroup())->toBe('Reports')
        ->and($plugin->getNavigationLabel())->toBe('Analytics');

    app()->setLocale('zh_CN');
    expect(StatsPlugin::make()->getNavigationLabel())->toBe('网站统计');
});

it('hides the widgets unless the viewStats gate allows', function () {
    expect(StatsOverview::canView())->toBeFalse()->and(TopSources::canView())->toBeFalse();
    Livewire::test(StatsOverview::class)->assertForbidden();
    Livewire::test(TopSources::class)->assertForbidden();

    Gate::define('viewStats', fn ($user = null) => true);
    expect(StatsOverview::canView())->toBeTrue()->and(TopSources::canView())->toBeTrue();
});

it('renders today and 7-day visitors and pageviews with changes over complete days', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    $site = S::site('Alpha');
    S::totals($site, '2026-03-10', ['visitors' => 5, 'visits' => 5, 'pageviews' => 7]);
    S::totals($site, '2026-03-09', ['visitors' => 30, 'visits' => 30, 'pageviews' => 90]);
    S::totals($site, '2026-03-08', ['visitors' => 20, 'visits' => 20, 'pageviews' => 90]);
    S::totals($site, '2026-03-01', ['visitors' => 100, 'visits' => 100, 'pageviews' => 360]);

    Livewire::test(StatsOverview::class)
        ->assertOk()
        ->assertSee('Site statistics')
        ->assertSee('Alpha · visitors · today')
        ->assertSee('+50.0% (Mar 9 vs Mar 8)')
        ->assertSee('±0.0% (Mar 9 vs Mar 8)')
        ->assertSee('Alpha · pageviews · 7 days')
        ->assertSee('187')
        ->assertSee('−50.0% (Mar 4–9 vs Feb 26 – Mar 3)')
        ->assertSee(e(StatsDashboard::urlFor($site->public_id, 'today')), false);
});

it('renders the widgets in Simplified Chinese', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    app()->setLocale('zh_CN');
    $site = S::site('Alpha');
    S::source($site, '2026-03-09', 'search', 'google.com', ['visits' => 5, 'visitors' => 5, 'pageviews' => 5, 'bounces' => 0]);

    Livewire::test(StatsOverview::class)->assertSee('站点统计')->assertSee('Alpha · 访客 · 今天')->assertSee('Alpha · 浏览量 · 7 天');
    Livewire::test(TopSources::class)->assertSee('主要来源 · 最近 7 天')->assertSee('搜索引擎')->assertSee('访问次数')->assertDontSee('stats::');
});

it('renders the top 5 sources of the last 7 days per site', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    $site = S::site('Alpha');
    S::site('Empty');
    foreach (range(1, 6) as $i) {
        S::source($site, '2026-03-09', 'referral', "ref{$i}.example", ['visits' => $i, 'visitors' => $i, 'pageviews' => $i, 'bounces' => 0]);
    }
    S::source($site, '2026-03-09', 'direct', null, ['visits' => 50, 'visitors' => 40, 'pageviews' => 60, 'bounces' => 0]);
    S::source($site, '2026-03-02', 'search', 'old.example', ['visits' => 999, 'visitors' => 999, 'pageviews' => 999, 'bounces' => 0]);

    Livewire::test(TopSources::class)
        ->assertOk()
        ->assertSee('Top sources · last 7 days')
        ->assertSeeInOrder(['(direct)', 'ref6.example', 'ref3.example'])
        ->assertDontSee('ref2.example')
        ->assertDontSee('old.example')
        ->assertSee(e(StatsDashboard::urlFor($site->public_id, '7d')), false);
});

it('shows an empty state when no site had visits', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    S::site('Empty');
    Livewire::test(TopSources::class)->assertSee('No visits in the last 7 days.');
});
