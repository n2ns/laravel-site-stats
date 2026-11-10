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

it('registers both widgets on a panel', function () {
    $panel = Panel::make()->id('admin');
    $plugin = StatsPlugin::make();
    $plugin->register($panel);

    expect($plugin->getId())->toBe('n2ns-stats')
        ->and($panel->getWidgets())->toContain(StatsOverview::class, TopSources::class);
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

it('renders today and 7-day visitors and pageviews with changes', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    $site = S::site('Alpha');
    S::totals($site, '2026-03-10', ['visitors' => 30, 'visits' => 30, 'pageviews' => 90]);
    S::totals($site, '2026-03-09', ['visitors' => 20, 'visits' => 20, 'pageviews' => 90]);
    S::totals($site, '2026-03-01', ['visitors' => 100, 'visits' => 100, 'pageviews' => 100]);

    Livewire::test(StatsOverview::class)
        ->assertOk()
        ->assertSee('Alpha · visitors · today')
        ->assertSee('+50.0% vs yesterday')
        ->assertSee('±0.0% vs yesterday')
        ->assertSee('Alpha · pageviews · 7 days')
        ->assertSee('180')
        ->assertSee('−50.0% vs previous 7 days')
        ->assertSee(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'today']), false);
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
        ->assertSee('Top sources')
        ->assertSee('(direct)')
        ->assertSee('ref6.example')
        ->assertSee('ref3.example')
        ->assertDontSee('ref2.example')
        ->assertDontSee('old.example')
        ->assertSee('No visits in the last 7 days.')
        ->assertSee(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']), false);
});
