<?php

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\LivewireServiceProvider;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\StatsPlugin;
use N2ns\Stats\Reports\StatsQuery;
use N2ns\Stats\Tests\StatsSeeder as S;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

beforeEach(function () {
    $this->app->register(new class($this->app) extends PanelProvider
    {
        public function panel(Panel $panel): Panel
        {
            return $panel->id('admin')->path('admin')->default()
                ->plugin(StatsPlugin::make()->navigationGroup('Reports')->navigationLabel('Analytics'));
        }
    });
    foreach ([
        BladeIconsServiceProvider::class,
        BladeHeroiconsServiceProvider::class,
        BladeCaptureDirectiveServiceProvider::class,
        LivewireServiceProvider::class,
        SupportServiceProvider::class,
        ActionsServiceProvider::class,
        SchemasServiceProvider::class,
        FormsServiceProvider::class,
        InfolistsServiceProvider::class,
        NotificationsServiceProvider::class,
        TablesServiceProvider::class,
        WidgetsServiceProvider::class,
        FilamentServiceProvider::class,
    ] as $provider) {
        $this->app->register($provider);
    }
    Carbon::setTestNow('2026-03-10 12:00:00');
});

it('refuses the statistics page without the viewStats gate', function () {
    S::site('Alpha');
    $this->get('/admin/stats')->assertForbidden();
    expect(StatsDashboard::canAccess())->toBeFalse();
});

it('renders the full dashboard inside the panel with the host navigation group and label', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    $site = S::site('Alpha');
    S::totals($site, '2026-03-09', ['visitors' => 1234, 'visits' => 1500, 'pageviews' => 4500, 'events' => 4, 'outbound' => 2]);
    S::source($site, '2026-03-09', 'search', 'google.com', ['visits' => 77, 'visitors' => 70, 'pageviews' => 90, 'bounces' => 7]);
    S::page($site, '2026-03-09', '/pricing', ['pageviews' => 321, 'visitors' => 200]);

    $response = $this->get('/admin/stats?range=7d')->assertOk();

    $response->assertSee('class="fi', false)                 // the Filament layout
        ->assertSee('Reports')                              // navigation group
        ->assertSee('Analytics')                            // navigation label and title
        ->assertSee('class="stats-dash stats-filament"', false)
        ->assertSee('<option value="'.$site->public_id.'" selected>Alpha</option>', false)
        ->assertSee('<option value="7d" selected>Last 7 days</option>', false)
        ->assertSee('<input type="hidden" name="site" value="'.$site->public_id.'">', false)
        ->assertSee('action="'.StatsDashboard::getUrl().'"', false)
        ->assertSee('Compared: Mar 4–9 vs Feb 26 – Mar 3, complete days only.')
        ->assertSeeInOrder(['Visitors', '1,234', 'Outbound clicks', '2', 'Realtime'])
        ->assertSee('Daily traffic')
        ->assertSee('<svg viewBox="0 0 800 240" role="img"', false)
        ->assertSee('<svg class="donut"', false)
        ->assertSee('google.com')
        ->assertSee('/pricing')
        ->assertSee('data-sortable', false)
        ->assertSee(route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => 'pages', 'range' => '7d']), false)
        ->assertSee('sets no cookies');
    foreach (StatsQuery::REPORTS as $report) {
        $response->assertSee('id="report-'.$report.'"', false);
    }
});

it('switches sites and ranges through query parameters', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    S::site('Alpha');
    $beta = S::site('Beta');

    $this->get('/admin/stats?site='.$beta->public_id.'&range=custom&from=2026-01-01&to=2026-01-31')
        ->assertOk()
        ->assertSee('<option value="'.$beta->public_id.'" selected>Beta</option>', false)
        ->assertSee('2026-01-01 – 2026-01-31', false);
    $this->get('/admin/stats?site=unknown&range[]=x')
        ->assertOk()
        ->assertSee('Alpha')
        ->assertSee('<option value="30d" selected>Last 30 days</option>', false);
});

it('links to the site manager when there are no sites', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    $this->get('/admin/stats')->assertOk()->assertSee('No sites yet.')->assertSee(route('stats.sites.index'), false);
});

it('renders the statistics page in Simplified Chinese', function () {
    Gate::define('viewStats', fn ($user = null) => true);
    app()->setLocale('zh_CN');
    S::site('Alpha');

    $this->get('/admin/stats?range=15d')->assertOk()
        ->assertSee('最近 15 天')
        ->assertSee('热门页面')
        ->assertDontSee('stats::');
});
