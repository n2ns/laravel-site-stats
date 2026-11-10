<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use N2ns\Stats\Models\Site;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'UTC'));
    $this->site = Site::create(['name' => 'Example', 'domains' => ['example.com']]);
    $id = $this->site->id;

    foreach (['2026-11-08', '2026-11-09', '2026-11-10', '2026-11-11'] as $date) {
        DB::table('stats_salts')->insert(['date' => $date, 'salt' => str_repeat('a', 64)]);
        DB::table('stats_uniques')->insert(['site_id' => $id, 'date' => $date, 'scope' => hash('sha256', 'site'), 'visitor_hash' => str_repeat('b', 64)]);
        DB::table('stats_daily_totals')->insert(['site_id' => $id, 'date' => $date, 'pageviews' => 1]);
        DB::table('stats_daily_pages')->insert(['site_id' => $id, 'date' => $date, 'path' => '/', 'path_hash' => hash('sha256', '/'), 'pageviews' => 1]);
        DB::table('stats_daily_sources')->insert(['site_id' => $id, 'date' => $date, 'source_key' => 'k', 'source_type' => 'direct']);
        DB::table('stats_daily_dimensions')->insert(['site_id' => $id, 'date' => $date, 'dimension' => 'os', 'value' => 'iOS']);
        DB::table('stats_daily_events')->insert(['site_id' => $id, 'date' => $date, 'kind' => 'custom', 'name' => 'x', 'target_hash' => 'h', 'path_hash' => 'p']);
    }

    foreach (['09:00:00' => 1, '09:45:00' => 1] as $lastSeen => $pageviews) {
        DB::table('stats_visits')->insert([
            'site_id' => $id, 'visitor_hash' => hash('sha256', $lastSeen), 'started_at' => '2026-11-11 08:59:00', 'date' => '2026-11-11',
            'last_seen_at' => '2026-11-11 '.$lastSeen, 'pageviews' => $pageviews, 'entry_path_hash' => hash('sha256', '/'),
            'exit_path_hash' => hash('sha256', '/'), 'source_key' => 'k',
        ]);
    }
});

it('closes idle visits and deletes expired salts and uniques', function () {
    $this->artisan('stats:maintain')
        ->expectsOutput('Closed visits: 1')
        ->expectsOutput('Deleted salts: 2')
        ->expectsOutput('Deleted uniques: 2')
        ->assertSuccessful();

    expect(DB::table('stats_visits')->count())->toBe(1)
        ->and(DB::table('stats_salts')->orderBy('date')->pluck('date')->all())->toBe(['2026-11-10', '2026-11-11'])
        ->and(DB::table('stats_uniques')->count())->toBe(2)
        ->and((int) DB::table('stats_daily_totals')->where('date', '2026-11-11')->value('bounces'))->toBe(1)
        ->and((int) DB::table('stats_daily_totals')->where('date', '2026-11-11')->value('duration_seconds'))->toBe(60)
        ->and(DB::table('stats_daily_totals')->count())->toBe(4);
});

it('keeps aggregates forever without a retention period', function () {
    config(['stats.retention_days' => null]);
    $this->artisan('stats:maintain')->assertSuccessful();

    foreach (['daily_totals', 'daily_pages', 'daily_sources', 'daily_dimensions', 'daily_events'] as $table) {
        expect(DB::table('stats_'.$table)->count())->toBe(4);
    }
});

it('deletes aggregates older than the retention period', function () {
    config(['stats.retention_days' => 2]);
    $this->artisan('stats:maintain')
        ->expectsOutput('Deleted daily_totals rows: 1')
        ->assertSuccessful();

    foreach (['daily_totals', 'daily_pages', 'daily_sources', 'daily_dimensions', 'daily_events'] as $table) {
        expect(DB::table('stats_'.$table)->min('date'))->toBe('2026-11-09');
    }
});
