<?php

use Illuminate\Support\Facades\DB;
use N2ns\Stats\Tests\StatsSeeder as S;

it('fills the entry visitors of days counted before the column existed from their landing pages', function () {
    $site = S::site();
    S::page($site, '2026-03-01', '/a', ['pageviews' => 9, 'visitors' => 7, 'entries' => 4]);
    S::page($site, '2026-03-01', '/b', ['pageviews' => 3, 'visitors' => 3, 'entries' => 0]);
    S::page($site, '2026-03-02', '/a', ['pageviews' => 2, 'visitors' => 2, 'entries' => 1]);
    S::entry($site, '2026-03-01', '/a', 'search', 'google.com', 3, 2);
    S::entry($site, '2026-03-01', '/a', 'direct', null, 1, 1);
    S::entry($site, '2026-03-02', '/c', 'direct', null, 1, 1); // another page
    $key = hash('sha256', implode('|', ['search', 'google.com', null])); // StatsSeeder::entry()'s source key
    DB::table('stats_uniques')->insert(['site_id' => $site->id, 'date' => '2026-03-01', 'scope' => hash('sha256', 'entry:'.hash('sha256', '/a').':'.$key), 'visitor_hash' => 'v1']);

    $migration = require __DIR__.'/../../database/migrations/2026_11_13_000000_add_entry_visitors_to_stats_daily_pages.php';
    $migration->fillFromLandingPages();

    expect(DB::table('stats_daily_pages')->orderBy('date')->orderBy('path')->get()->map(fn ($r) => [$r->date, $r->path, (int) $r->entry_visitors])->all())
        ->toBe([['2026-03-01', '/a', 3], ['2026-03-01', '/b', 0], ['2026-03-02', '/a', 0]])
        ->and(DB::table('stats_uniques')->where('scope', hash('sha256', 'entry-page:'.hash('sha256', '/a')))->pluck('visitor_hash')->all())->toBe(['v1']); // not counted again today
});
