<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use N2ns\Stats\Tests\StatsSeeder as S;
use N2ns\Stats\Tests\TestCase;

// The dashboard and site pages inside the host's own admin layout (dashboard_layout), with a link back to it.
afterEach(fn () => TestCase::$config = []);

beforeEach(function () {
    View::addLocation(__DIR__.'/../views');
    Gate::define('viewStats', fn ($user = null) => true);
});

it('shows the pages standalone, without a back link, by default', function () {
    $site = S::site('Alpha');

    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSee('<header class="stats-appbar">', false)
        ->assertDontSee('class="stats-back"', false);
});

it('links back to the configured address on the standalone pages', function () {
    config(['stats.dashboard_back_url' => '/admin']);
    $site = S::site('Alpha');

    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSee('<a class="stats-back" href="'.url('/admin').'">', false)
        ->assertSee('Back');
    config(['stats.dashboard_back_label' => 'Admin home']);
    $this->get('/stats/manage/sites')->assertOk()->assertSee('Admin home');
});

it('puts the pages into a host layout that is extended, with the title in its title sections', function () {
    config(['stats.dashboard_layout' => ['view' => 'host-admin', 'section' => 'body', 'title_sections' => ['title', 'page-title']], 'stats.dashboard_back_url' => '/admin']);
    $site = S::site('Alpha');

    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSee('<title>Alpha - Host admin</title>', false)
        ->assertSee('<h1 class="host-title">Alpha</h1>', false)
        ->assertSeeInOrder(['Host sidebar', '<main>', 'stats-dash stats-embedded', 'stats-back', 'Site settings', 'kpis'], false)
        ->assertDontSee('<header class="stats-appbar">', false);
    $this->get('/stats/manage/sites')->assertOk()
        ->assertSee('<title>Site settings - Host admin</title>', false)
        ->assertSeeInOrder(['Host sidebar', 'stats-embedded', 'data-copy'], false);
});

it('puts the pages into a host layout component, with the title as its title attribute', function () {
    config(['stats.dashboard_layout' => ['component' => 'host-shell']]);
    $site = S::site('Alpha');

    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSeeInOrder(['<div class="host-shell">', 'Host menu', '<h1 class="host-title">Alpha</h1>', 'stats-dash stats-embedded'], false)
        ->assertDontSee('class="stats-back"', false)
        ->assertDontSee('<header class="stats-appbar">', false);
});

it('fills fixed text into other sections of the host layout', function () {
    config(['stats.dashboard_layout' => ['view' => 'host-admin', 'section' => 'body', 'title_sections' => ['title'], 'sections' => ['page-title' => 'Analytics']]]);
    $site = S::site('Alpha');

    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSee('<title>Alpha - Host admin</title>', false)
        ->assertSee('<h1 class="host-title">Analytics</h1>', false);
});

it('keeps the light theme in a host layout unless the layout follows the system theme', function () {
    config(['stats.dashboard_layout' => ['view' => 'host-admin', 'section' => 'body']]);
    $site = S::site('Alpha');

    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSee('<div class="stats-dash stats-embedded">', false);

    config(['stats.dashboard_layout' => ['view' => 'host-admin', 'section' => 'body', 'theme' => 'system']]);
    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSee('<div class="stats-dash stats-embedded stats-theme-system">', false)
        ->assertSee('.stats-dash.stats-embedded.stats-theme-system{', false);
    $this->get('/stats/manage/sites')->assertOk()->assertSee('stats-embedded stats-theme-system', false);
});

it('prefixes every class of the pages, so that the host layout\'s styles do not reach them', function () {
    config(['stats.dashboard_layout' => ['view' => 'host-admin', 'section' => 'body', 'theme' => 'system']]);
    $site = S::site('Alpha');
    $d = now()->subDay()->toDateString();
    S::totals($site, $d, ['visitors' => 4, 'visits' => 5, 'pageviews' => 9, 'events' => 1, 'outbound' => 1]);
    S::page($site, $d, '/a', ['pageviews' => 9, 'visitors' => 4, 'entries' => 5, 'exits' => 5, 'title' => 'A', 'page_type' => 'doc']);
    S::source($site, $d, 'search', 'google.com', ['visits' => 5, 'visitors' => 4, 'pageviews' => 9, 'bounces' => 1]);
    S::dimension($site, $d, 'country', 'DE', 5, 4, 9);
    S::event($site, $d, 'custom', 'signup', null, '/a', 1, 1);

    foreach (['/stats/sites/'.$site->public_id, '/stats/manage/sites'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        $body = substr($html, strpos($html, '<div class="stats-dash'));
        preg_match_all('/\sclass="([^"]*)"/', $body, $m);
        $classes = array_unique(preg_split('/\s+/', trim(implode(' ', $m[1]))));
        expect(array_values(array_filter($classes, fn (string $c) => ! str_starts_with($c, 'stats-'))))->toBe([], $url);
        // Class selectors in the styles, and in the scripts' quoted selectors and class names too.
        $styles = implode(' ', preg_match_all('/<style>(.*?)<\/style>/s', $html, $s) ? $s[1] : []);
        $quoted = implode(' ', preg_match_all("/'([^'\n]*)'/", implode(' ', preg_match_all('/<script>(.*?)<\/script>/s', $html, $j) ? $j[1] : []), $q) ? $q[1] : []);
        preg_match_all('/(?<![\w-])\.([a-z][\w-]*)(?=[\s,{:.>\[#)]|$)/', $styles.' '.$quoted, $sel);
        $unprefixed = array_filter(array_unique($sel[1]), fn (string $c) => ! str_starts_with($c, 'stats-') && ! str_starts_with($c, 'fi-') && $c !== 'dark');
        expect(array_values($unprefixed))->toBe([], $url);
    }
});
