<?php

namespace N2ns\Stats\Support;

/** Prefixed table names, e.g. Tables::name('daily_totals') => 'stats_daily_totals'. */
final class Tables
{
    public static function name(string $table): string
    {
        return config('stats.table_prefix', 'stats_').$table;
    }
}
