<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visitors who entered the site on each page and day (the entry pages report). Days counted before this column existed
 * get the sum of their landing-page rows, which counts a visitor once per source. Visitors already deduplicated for a
 * landing page are also deduplicated for its entry page, so they are not counted again on the day of the upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('stats.table_prefix', 'stats_').'daily_pages', function (Blueprint $t) {
            $t->unsignedBigInteger('entry_visitors')->default(0);
        });
        $this->fillFromLandingPages();
    }

    public function fillFromLandingPages(): void
    {
        $prefix = config('stats.table_prefix', 'stats_');
        [$pages, $entries, $uniques] = [$prefix.'daily_pages', $prefix.'daily_entries', $prefix.'uniques'];

        // No table alias: a connection prefix would be added to the alias but not to raw column references.
        $sum = DB::table($entries)->selectRaw('COALESCE(SUM(visitors), 0)')
            ->whereColumn($entries.'.site_id', $pages.'.site_id')
            ->whereColumn($entries.'.date', $pages.'.date')
            ->whereColumn($entries.'.path_hash', $pages.'.path_hash');
        DB::table($pages)->where('entries', '>', 0)->update(['entry_visitors' => DB::raw('('.$sum->toSql().')')]);

        // Deduplication rows exist only for the last days (stats:maintain removes older ones).
        $since = DB::table($uniques)->min('date');
        if ($since === null) {
            return;
        }
        $rows = DB::table($entries)->where('date', '>=', $since)->get(['site_id', 'date', 'path_hash', 'source_key']);
        foreach ($rows as $row) {
            $visitors = DB::table($uniques)
                ->where(['site_id' => $row->site_id, 'date' => $row->date, 'scope' => hash('sha256', 'entry:'.$row->path_hash.':'.$row->source_key)])
                ->pluck('visitor_hash');
            $scope = hash('sha256', 'entry-page:'.$row->path_hash);
            foreach ($visitors->chunk(500) as $chunk) {
                DB::table($uniques)->insertOrIgnore($chunk->map(fn ($visitor): array => [
                    'site_id' => $row->site_id, 'date' => $row->date, 'scope' => $scope, 'visitor_hash' => $visitor,
                ])->values()->all());
            }
        }
    }

    public function down(): void
    {
        Schema::table(config('stats.table_prefix', 'stats_').'daily_pages', function (Blueprint $t) {
            $t->dropColumn('entry_visitors');
        });
    }
};
