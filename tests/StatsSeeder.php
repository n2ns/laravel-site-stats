<?php

namespace N2ns\Stats\Tests;

use Illuminate\Support\Facades\DB;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\Tables;

/** Inserts daily aggregate rows directly, for dashboard, API and report tests. */
final class StatsSeeder
{
    /** @param array<string, mixed> $attributes */
    public static function site(string $name = 'Example', array $attributes = []): Site
    {
        return Site::create($attributes + ['name' => $name, 'domains' => ['example.com']]);
    }

    /** @param array<string, int> $values */
    public static function totals(Site $site, string $date, array $values): void
    {
        DB::table(Tables::name('daily_totals'))->insert(['site_id' => $site->id, 'date' => $date] + $values);
    }

    /** @param array<string, int> $values */
    public static function page(Site $site, string $date, string $path, array $values): void
    {
        DB::table(Tables::name('daily_pages'))->insert(['site_id' => $site->id, 'date' => $date, 'path' => $path, 'path_hash' => hash('sha256', $path)] + $values);
    }

    /**
     * @param  array<string, int>  $values
     * @param  array<string, string|null>  $utm
     */
    public static function source(Site $site, string $date, string $type, ?string $source, array $values, array $utm = []): void
    {
        DB::table(Tables::name('daily_sources'))->insert([
            'site_id' => $site->id, 'date' => $date, 'source_type' => $type, 'source' => $source,
            'source_key' => hash('sha256', implode('|', [$type, $source, ...array_values($utm)])),
        ] + $utm + $values);
    }

    public static function dimension(Site $site, string $date, string $dimension, string $value, int $visits, int $visitors): void
    {
        DB::table(Tables::name('daily_dimensions'))->insert(compact('dimension', 'value', 'visits', 'visitors', 'date') + ['site_id' => $site->id]);
    }

    public static function event(Site $site, string $date, string $kind, string $name, ?string $target, ?string $path, int $count, int $visitors = 0): void
    {
        DB::table(Tables::name('daily_events'))->insert([
            'site_id' => $site->id, 'date' => $date, 'kind' => $kind, 'name' => $name,
            'target' => $target, 'target_hash' => hash('sha256', (string) $target),
            'path' => $path, 'path_hash' => hash('sha256', (string) $path),
            'count' => $count, 'visitors' => $visitors,
        ]);
    }

    public static function visit(Site $site, string $visitor, \DateTimeInterface $lastSeen): void
    {
        DB::table(Tables::name('visits'))->insert([
            'site_id' => $site->id, 'visitor_hash' => $visitor, 'started_at' => $lastSeen, 'date' => $lastSeen->format('Y-m-d'),
            'last_seen_at' => $lastSeen, 'pageviews' => 1, 'entry_path_hash' => 'x', 'exit_path_hash' => 'x', 'source_key' => 'x',
        ]);
    }
}
