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
        ->assertSee('<header class="appbar">', false)
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
        ->assertDontSee('<header class="appbar">', false);
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
        ->assertDontSee('<header class="appbar">', false);
});

it('fills fixed text into other sections of the host layout', function () {
    config(['stats.dashboard_layout' => ['view' => 'host-admin', 'section' => 'body', 'title_sections' => ['title'], 'sections' => ['page-title' => 'Analytics']]]);
    $site = S::site('Alpha');

    $this->get('/stats/sites/'.$site->public_id)->assertOk()
        ->assertSee('<title>Alpha - Host admin</title>', false)
        ->assertSee('<h1 class="host-title">Analytics</h1>', false);
});
