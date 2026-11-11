<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use N2ns\Stats\Support\Source;
use N2ns\Stats\Support\Tables;

/**
 * Moves sources counted before AI assistants' utm_source tags were recognised (utm_source=chatgpt.com and no medium
 * or campaign, see SourceClassifier) from UTM campaigns to AI referrals: the daily source and landing-page rows, and
 * the visits still open, with their deduplication rows so the visitors are not counted again. A row whose AI source
 * already has a row that day is added to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $keys = [];
            $rows = DB::table(Tables::name('daily_sources'))->where('source_type', 'utm')->whereNull('utm_medium')->whereNull('utm_campaign')->orderBy('id')->get();
            foreach ($rows as $row) {
                $ai = $this->aiSource((string) $row->source);
                if ($ai === null) {
                    continue;
                }
                $keys[(string) $row->source_key] = $ai->key();
                $this->move('daily_sources', $row, ['site_id', 'date'], $ai, ['visits', 'visitors', 'pageviews', 'bounces', 'outbound', 'events'], [
                    'source_type' => 'ai', 'source' => $ai->source, 'utm_medium' => null, 'utm_campaign' => null, 'utm_content' => null, 'utm_term' => null,
                ]);
            }
            // Landing pages have no medium column, so they are matched by the source keys moved above.
            $entries = $keys === [] ? [] : DB::table(Tables::name('daily_entries'))->whereIn('source_key', array_keys($keys))->orderBy('id')->get();
            foreach ($entries as $row) {
                $ai = $this->aiSource((string) $row->source);
                if ($ai === null) {
                    continue;
                }
                $this->move('daily_entries', $row, ['site_id', 'date', 'path_hash'], $ai, ['visits', 'visitors'], [
                    'source_type' => 'ai', 'source' => $ai->source, 'utm_campaign' => null,
                ]);
                $this->moveUniques('entry:'.$row->path_hash.':'.$row->source_key, 'entry:'.$row->path_hash.':'.$ai->key());
            }
            foreach ($keys as $old => $new) {
                DB::table(Tables::name('visits'))->where('source_key', $old)->update(['source_key' => $new]);
                $this->moveUniques('source:'.$old, 'source:'.$new);
            }
        });
    }

    public function down(): void
    {
        // The UTM rows cannot be told apart from the AI referrals they were added to.
    }

    /** Moves the visitors deduplicated in one scope to another (as Recorder::bump() names them), keeping each once. */
    private function moveUniques(string $from, string $to): void
    {
        $table = Tables::name('uniques');
        $old = DB::table($table)->where('scope', hash('sha256', $from))->get(['site_id', 'date', 'visitor_hash']);
        foreach ($old->chunk(500) as $chunk) {
            DB::table($table)->insertOrIgnore($chunk->map(fn ($row): array => [
                'site_id' => $row->site_id, 'date' => $row->date, 'scope' => hash('sha256', $to), 'visitor_hash' => $row->visitor_hash,
            ])->values()->all());
        }
        DB::table($table)->where('scope', hash('sha256', $from))->delete();
    }

    private function aiSource(string $utmSource): ?Source
    {
        $host = (string) preg_replace('/^www\./', '', strtolower($utmSource));

        return in_array($host, (array) config('stats.ai_hosts', []), true) ? new Source('ai', $host) : null;
    }

    /**
     * @param  list<string>  $scope  columns that, with source_key, identify the row's day
     * @param  list<string>  $counters
     * @param  array<string, string|null>  $fields
     */
    private function move(string $table, object $row, array $scope, Source $ai, array $counters, array $fields): void
    {
        $table = Tables::name($table);
        $where = ['source_key' => $ai->key()];
        foreach ($scope as $column) {
            $where[$column] = $row->{$column};
        }
        $target = DB::table($table)->where($where)->first(['id']);
        if ($target === null) {
            DB::table($table)->where('id', $row->id)->update(['source_key' => $ai->key()] + $fields);

            return;
        }
        $add = [];
        foreach ($counters as $counter) {
            $add[$counter] = (int) $row->{$counter};
        }
        DB::table($table)->where('id', $target->id)->incrementEach($add);
        DB::table($table)->where('id', $row->id)->delete();
    }
};
