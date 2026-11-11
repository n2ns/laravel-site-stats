<?php

namespace N2ns\Stats\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use N2ns\Stats\Recording\Recorder;
use N2ns\Stats\Support\Tables;

class MaintainCommand extends Command
{
    protected $signature = 'stats:maintain';

    protected $description = 'Close idle visits, delete expired salts and uniques, apply the retention period';

    public function handle(Recorder $recorder): int
    {
        $now = CarbonImmutable::now();

        $closed = $recorder->closeIdleVisits($now);

        // Salts and uniques are keyed by site-local dates. Keeping UTC yesterday and today covers every offset
        // (UTC-12..+14) while the date is in use; a local day's hashes are gone within 48 hours after it ends.
        $cutoff = $now->utc()->subDay()->toDateString();
        $salts = DB::table(Tables::name('salts'))->where('date', '<', $cutoff)->delete();
        $uniques = DB::table(Tables::name('uniques'))->where('date', '<', $cutoff)->delete();

        $this->info("Closed visits: {$closed}");
        $this->info("Deleted salts: {$salts}");
        $this->info("Deleted uniques: {$uniques}");

        $retention = config('stats.retention_days');
        if ($retention !== null && $retention !== '' && (int) $retention > 0) {
            $before = $now->utc()->subDays((int) $retention)->toDateString();
            foreach (['daily_totals', 'daily_pages', 'daily_sources', 'daily_dimensions', 'daily_events', 'daily_entries', 'daily_transitions'] as $table) {
                $deleted = DB::table(Tables::name($table))->where('date', '<', $before)->delete();
                $this->info("Deleted {$table} rows: {$deleted}");
            }
        }

        return self::SUCCESS;
    }
}
