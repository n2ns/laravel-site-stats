<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time on page and scroll depth per page and day: how many page views reported them, their total seconds, and how
 * many reached each time and scroll threshold.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'engaged_views', 'engaged_seconds', 'time_10', 'time_30', 'time_60', 'time_180',
        'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100',
    ];

    public function up(): void
    {
        Schema::table(config('stats.table_prefix', 'stats_').'daily_pages', function (Blueprint $t) {
            foreach (self::COLUMNS as $column) {
                $t->unsignedBigInteger($column)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table(config('stats.table_prefix', 'stats_').'daily_pages', function (Blueprint $t) {
            $t->dropColumn(self::COLUMNS);
        });
    }
};
