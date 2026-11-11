<?php

use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\StatsPlugin;
use N2ns\Stats\Filament\Widgets\StatsOverview;
use N2ns\Stats\Filament\Widgets\TopSources;
use N2ns\Stats\Tests\StatsSeeder as S;

beforeEach(function () {
    foreach ([
        LivewireServiceProvider::class,
        SupportServiceProvider::class,
        ActionsServiceProvider::class,
        SchemasServiceProvider::class,
        FormsServiceProvider::class,
        InfolistsServiceProvider::class,
        NotificationsServiceProvider::class,
        TablesServiceProvider::class,
        WidgetsServiceProvider::class,
    ] as $provider) {
        $this->app->register($provider);
    }
    Carbon::setTestNow('2026-03-10 12:00:00');
});

it('registers both widgets and the statistics page on a panel', function () {
    $panel = Panel::make()->id('admin');
    $plugin = StatsPlugin::make();
    $plugin->register($panel);

    expect($plugin->getId())->toBe('n2ns-stats')
        ->and($panel->getWidgets())->toContain(StatsOverview::class, TopSources::class)
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
    Gate::define('viewStats', fn ($user = null) => true);
    expect(StatsOverview::canView())->toBeTrue()->and(TopSources::canView())->toBeTrue();
});

it('refuses to mount the widgets without the viewStats gate', function () {
    Livewire::test(StatsOverview::class)->assertForbidden();
    Livewire::test(TopSources::class)->assertForbidden();
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
        ->assertSee(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'today']), false);
});

it('renders the widgets in Simplified Chinese', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    app()->setLocale('zh_CN');
    $site = S::site('Alpha');
    S::source($site, '2026-03-09', 'search', 'google.com', ['visits' => 5, 'visitors' => 5, 'pageviews' => 5, 'bounces' => 0]);

    Livewire::test(StatsOverview::class)->assertSee('站点统计')->assertSee('Alpha · 访客 · 今天')->assertSee('Alpha · 浏览量 · 7 天');
    Livewire::test(TopSources::class)->assertSee('主要来源 · 最近 7 天')->assertSee('搜索引擎')->assertSee('访问次数');
});

it('renders the top 5 sources of the last 7 days per site', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    $site = S::site('Alpha');
    S::site('Empty');
    foreach (range(1, 6) as $i) {
        S::source($site, '2026-03-09', 'referral', "ref{$i}.example", ['visits' => $i, 'visitors' => $i, 'pageviews' => $i, 'bounces' => 0]);
    }
    S::source($site, '2026-03-09', 'direct', null, ['visits' => 50, 'visitors' => 40, 'pageviews' => 60, 'bounces' => 0]);
    S::source($site, '2026-02-01', 'search', 'old.example', ['visits' => 999, 'visitors' => 999, 'pageviews' => 999, 'bounces' => 0]);

    Livewire::test(TopSources::class)
        ->assertOk()
        ->assertSee('Top sources · last 7 days')
        ->assertSee('(direct)')
        ->assertSee('ref6.example')
        ->assertSee('ref3.example')
        ->assertDontSee('ref2.example')
        ->assertDontSee('old.example')
        ->assertSee('No visits in the last 7 days.')
        ->assertSee(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']), false);
});
