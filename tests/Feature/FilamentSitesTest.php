<?php

use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use N2ns\Stats\Console\SiteCommand;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\Resources\Sites\Pages\CreateSite;
use N2ns\Stats\Filament\Resources\Sites\Pages\EditSite;
use N2ns\Stats\Filament\Resources\Sites\Pages\ListSites;
use N2ns\Stats\Filament\Resources\Sites\Pages\ViewSite;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\Tables;
use N2ns\Stats\Tests\Feature\FilamentPanel;
use N2ns\Stats\Tests\StatsSeeder as S;
use N2ns\Stats\Tests\TestCase;

// The host turned the standalone dashboard off: the resource must not need its routes.
beforeAll(fn () => TestCase::$config = ['stats.dashboard_enabled' => false]);
afterAll(fn () => TestCase::$config = []);

beforeEach(function () {
    FilamentPanel::register($this->app, fn ($plugin) => $plugin->navigationGroup('Reports'));
    Carbon::setTestNow('2026-03-10 12:00:00');
});

it('refuses the resource and its actions without the viewStats gate', function () {
    $site = S::site('Alpha');
    $this->get('/admin/stats-sites')->assertForbidden();
    $this->get('/admin/stats-sites/create')->assertForbidden();
    $this->get('/admin/stats-sites/'.$site->public_id)->assertForbidden();
    $this->get('/admin/stats-sites/'.$site->public_id.'/edit')->assertForbidden();
    expect(SiteResource::canViewAny())->toBeFalse()
        ->and(SiteResource::canCreate())->toBeFalse()
        ->and(SiteResource::canEdit($site))->toBeFalse()
        ->and(SiteResource::canDelete($site))->toBeFalse();
});

it('lists the sites in the navigation group with a link to their statistics', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha', ['domains' => ['alpha.com', '*.alpha.org'], 'timezone' => 'Asia/Shanghai', 'locales' => ['en', 'zh-cn']]);
    S::site('Beta');

    $this->get('/admin/stats-sites')->assertOk()
        ->assertSee('Reports')
        ->assertSeeInOrder(['Alpha', 'alpha.com', '*.alpha.org', 'Asia/Shanghai', 'zh-cn', 'Beta'])
        ->assertSee(e(StatsDashboard::urlFor($site->public_id)), false);
    Livewire::test(ListSites::class)->assertCanSeeTableRecords(Site::all());
});

it('opens a site from the links of the list', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');

    $html = $this->get('/admin/stats-sites')->assertOk()->getContent();
    preg_match_all('#href="(http://localhost/admin/stats-sites/[^"]+)"#', $html, $links);
    $links = array_values(array_unique(array_filter($links[1], fn (string $url): bool => ! str_ends_with($url, '/create'))));

    expect($links)->toBe([
        'http://localhost/admin/stats-sites/'.$site->public_id,
        'http://localhost/admin/stats-sites/'.$site->public_id.'/edit',
    ]);
    foreach ($links as $link) {
        $this->get($link)->assertOk()->assertSee('Alpha');
    }
});

it('creates a site with the same normalisation as the standalone site manager', function () {
    FilamentPanel::allowStats();

    Livewire::test(CreateSite::class)
        ->fillForm([
            'name' => '  Docs ',
            'domains' => ['Docs.Example.com', ' *.example.org ', 'docs.example.com'],
            'timezone' => 'Europe/Berlin',
            'locales' => ['EN', 'zh-CN'],
            'default_locale' => ' EN ',
            'exclude_paths' => ['/admin/*', '/Preview'],
            'public_counts' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $site = Site::query()->sole();
    expect($site->name)->toBe('Docs')
        ->and($site->domains)->toBe(['docs.example.com', '*.example.org'])
        ->and($site->timezone)->toBe('Europe/Berlin')
        ->and($site->locales)->toBe(['en', 'zh-cn'])
        ->and($site->default_locale)->toBe('en')
        ->and($site->exclude_paths)->toBe(['/admin/*', '/Preview'])
        ->and($site->public_counts)->toBeTrue();
});

it('validates the fields like the standalone site manager', function () {
    FilamentPanel::allowStats();

    Livewire::test(CreateSite::class)
        ->fillForm(['name' => '', 'domains' => [], 'timezone' => 'Mars/Base'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'domains', 'timezone']);
    Livewire::test(CreateSite::class)
        ->fillForm([
            'name' => str_repeat('n', 121),
            'domains' => ['not a host', '1.2'],
            'timezone' => 'UTC',
            'locales' => ['english'],
            'default_locale' => 'x_y',
            'exclude_paths' => ['admin', '/'.str_repeat('a', 200)],
        ])
        ->call('create')
        ->assertHasFormErrors(['name', 'domains', 'locales', 'default_locale', 'exclude_paths'])
        ->assertSee('“not a host” is not a hostname (example.com) or wildcard (*.example.com).')
        ->assertSee('“english” is not a language code like en, pt-br or zh-hans.')
        ->assertSee('“admin” must start with / and be at most 200 characters long.');
    Livewire::test(CreateSite::class)
        ->fillForm(['name' => 'Many', 'domains' => array_map(fn ($i) => "d{$i}.example.com", range(1, 51)), 'timezone' => 'UTC', 'locales' => array_map(fn ($i) => 'l'.chr(97 + $i % 26).chr(97 + intdiv($i, 26)), range(1, 21))])
        ->call('create')
        ->assertHasFormErrors(['domains', 'locales'])
        ->assertSee('Enter at most 50 domains.')
        ->assertSee('Enter at most 20 language versions.');
    expect(Site::query()->count())->toBe(0);
});

it('edits a site', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');

    Livewire::test(EditSite::class, ['record' => $site->public_id])
        ->assertSchemaStateSet(['name' => 'Alpha', 'domains' => ['example.com'], 'timezone' => 'UTC'])
        ->fillForm(['name' => 'Alpha 2', 'domains' => ['alpha.com'], 'locales' => ['en']])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($site->refresh()->name)->toBe('Alpha 2')->and($site->domains)->toBe(['alpha.com'])->and($site->locales)->toBe(['en']);

    Livewire::test(EditSite::class, ['record' => $site->public_id])
        ->fillForm(['domains' => ['bad_host']])
        ->call('save')
        ->assertHasFormErrors(['domains']);
    expect($site->refresh()->domains)->toBe(['alpha.com']);
});

it('shows the embed snippet of the collector script', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');

    $this->get('/admin/stats-sites/'.$site->public_id)->assertOk()
        ->assertSee(SiteCommand::snippet($site))
        ->assertSee(route('stats.script'), false)
        ->assertSee('Embed code')
        ->assertSee(e(StatsDashboard::urlFor($site->public_id)), false);
});

it('generates a server key, shows it once and stores only its hash', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');

    $page = Livewire::test(ViewSite::class, ['record' => $site->public_id])
        ->assertActionHasLabel('rotateKey', 'Generate server key')
        ->callAction('rotateKey')
        ->assertActionMounted('serverKey')
        ->assertMountedActionModalSee(['New server key', 'STATS_MODE=client', 'STATS_CLIENT_ENDPOINT=http://localhost', 'STATS_CLIENT_SITE='.$site->public_id]);

    preg_match('/STATS_CLIENT_KEY=([A-Za-z0-9]{40})/', (string) $page->instance()->mountedActions[0]['arguments']['env'], $match);
    expect($match)->toHaveCount(2);
    $site->refresh();
    expect($site->server_key_hash)->toBe(hash('sha256', $match[1]))
        ->and($site->verifiesServerKey($match[1]))->toBeTrue()
        ->and(DB::table(Tables::name('sites'))->where('id', $site->id)->value('server_key_hash'))->not->toContain($match[1]);

    $this->get('/admin/stats-sites/'.$site->public_id)->assertOk()->assertDontSee($match[1])->assertSee('Rotate server key');

    Livewire::test(ViewSite::class, ['record' => $site->public_id])->callAction('rotateKey');
    expect($site->refresh()->verifiesServerKey($match[1]))->toBeFalse();
});

it('deletes a site with all of its statistics', function () {
    FilamentPanel::allowStats();
    $site = S::site('Alpha');
    $other = S::site('Beta');
    foreach ([$site, $other] as $s) {
        S::totals($s, '2026-03-09', ['visitors' => 1, 'visits' => 1, 'pageviews' => 1]);
        S::page($s, '2026-03-09', '/a', ['pageviews' => 1, 'visitors' => 1]);
        S::source($s, '2026-03-09', 'direct', null, ['visits' => 1, 'visitors' => 1, 'pageviews' => 1, 'bounces' => 0]);
        S::event($s, '2026-03-09', 'custom', 'signup', null, '/a', 1, 1);
    }

    Livewire::test(ListSites::class)->callAction(TestAction::make(DeleteAction::class)->table($site));

    expect(Site::query()->pluck('name')->all())->toBe(['Beta']);
    foreach (['daily_totals', 'daily_pages', 'daily_sources', 'daily_events'] as $table) {
        expect(DB::table(Tables::name($table))->where('site_id', $site->id)->count())->toBe(0)
            ->and(DB::table(Tables::name($table))->where('site_id', $other->id)->count())->toBe(1);
    }

    Livewire::test(ViewSite::class, ['record' => $other->public_id])->callAction(DeleteAction::class);
    expect(Site::query()->count())->toBe(0)->and(DB::table(Tables::name('daily_totals'))->count())->toBe(0);
});

it('renders the resource in Simplified Chinese', function () {
    FilamentPanel::allowStats();
    app()->setLocale('zh_CN');
    $site = S::site('Alpha');

    $this->get('/admin/stats-sites')->assertOk()->assertSee('站点')->assertSee('公开计数')->assertDontSee('stats::');
    $this->get('/admin/stats-sites/'.$site->public_id)->assertOk()->assertSee('嵌入代码')->assertSee('生成服务器密钥')->assertDontSee('stats::');
    $this->get('/admin/stats-sites/create')->assertOk()->assertSee('排除的路径')->assertDontSee('stats::');
});

it('opens the sites from a Site settings button on the statistics page instead of the navigation', function () {
    FilamentPanel::allowStats();
    S::site('Alpha');

    expect(SiteResource::shouldRegisterNavigation())->toBeFalse();
    Livewire::test(StatsDashboard::class)
        ->assertActionVisible('siteSettings')
        ->assertActionHasLabel('siteSettings', 'Site settings')
        ->assertActionHasUrl('siteSettings', SiteResource::getUrl('index'));
});
