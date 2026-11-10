<?php

namespace N2ns\Stats\Recording;

use Carbon\CarbonImmutable;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\Source;
use N2ns\Stats\Support\Tables;
use stdClass;
use Throwable;

/** Writes visits, uniques and daily aggregates (design §5.3). Dates are the site's local dates. */
class Recorder
{
    use DetectsConcurrencyErrors;

    private const ATTEMPTS = 3;

    /**
     * @param  array{country: string, device: string, browser: string, os: string, language: string}  $dimensions
     */
    public function recordPageview(Site $site, string $visitorHash, CarbonImmutable $now, string $path, string $pageUrl, Source $source, array $dimensions): void
    {
        $date = $this->localDate($site, $now);
        $nowUtc = $this->utc($now);
        $pathHash = hash('sha256', $path);

        // Finding or opening the visit is serialised per visitor: two parallel first pageviews (an SPA
        // replaceState right after load, restored tabs) would otherwise open two visits in different seconds.
        [$visit, $created] = Cache::lock('stats-visit:'.$site->id.':'.$visitorHash, 10)
            ->block(5, fn (): array => $this->findOrOpenVisit($site, $visitorHash, $now, $date, $nowUtc, $pathHash, $source));
        if ($created) {
            $this->countNewVisit($site, $visitorHash, $date, $path, $pathHash, $source, $dimensions);
        }

        $sourceKey = $visit === null ? $source->key() : (string) $visit->source_key;
        $startedAt = $visit === null ? $nowUtc : (string) $visit->started_at;
        $this->recordPageviewCounters($site, $visitorHash, $date, $nowUtc, $path, $pathHash, $source, $visit, $sourceKey, $startedAt);
    }

    /** @return array{0: stdClass|null, 1: bool} the open visit (null when just created) and whether it was created */
    private function findOrOpenVisit(Site $site, string $visitorHash, CarbonImmutable $now, string $date, string $nowUtc, string $pathHash, Source $source): array
    {
        $visit = $this->openVisit($site, $visitorHash, $now);
        if ($visit === null) {
            $created = DB::table(Tables::name('visits'))->insertOrIgnore([
                'site_id' => $site->id,
                'visitor_hash' => $visitorHash,
                'started_at' => $nowUtc,
                'date' => $date,
                'last_seen_at' => $nowUtc,
                'pageviews' => 0,
                'entry_path_hash' => $pathHash,
                'exit_path_hash' => $pathHash,
                'source_key' => $source->key(),
            ]);

            if ($created === 0) {
                return [$this->openVisit($site, $visitorHash, $now), false];
            }

            return [null, true];
        }

        return [$visit, false];
    }

    private function recordPageviewCounters(Site $site, string $visitorHash, string $date, string $nowUtc, string $path, string $pathHash, Source $source, ?stdClass $visit, string $sourceKey, string $startedAt): void
    {
        $this->bump('daily_totals', ['site_id' => $site->id, 'date' => $date], ['pageviews' => 1], [], [$site->id, $date, 'site', $visitorHash]);
        $this->bump('daily_pages', ['site_id' => $site->id, 'date' => $date, 'path_hash' => $pathHash], ['pageviews' => 1], ['path' => $path], [$site->id, $date, 'page:'.$pathHash, $visitorHash]);
        $this->bump(
            'daily_sources',
            ['site_id' => $site->id, 'date' => $date, 'source_key' => $sourceKey],
            ['pageviews' => 1],
            $visit === null ? $this->sourceFields($source) : $this->knownSourceFields($site->id, $sourceKey),
            [$site->id, $date, 'source:'.$sourceKey, $visitorHash],
        );

        $this->retry(fn () => DB::table(Tables::name('visits'))
            ->where(['site_id' => $site->id, 'visitor_hash' => $visitorHash, 'started_at' => $startedAt])
            ->incrementEach(['pageviews' => 1], ['last_seen_at' => $nowUtc, 'exit_path_hash' => $pathHash]));
    }

    /**
     * @param  'outbound'|'download'|'custom'|'ai_fetch'  $kind
     * @param  string|null  $visitorHash  null for ai_fetch
     */
    public function recordEvent(Site $site, ?string $visitorHash, CarbonImmutable $now, string $kind, string $name, ?string $target, ?string $path): void
    {
        $date = $this->localDate($site, $now);
        $targetHash = hash('sha256', $target ?? '');
        $pathHash = hash('sha256', $path ?? '');

        $this->bump(
            'daily_events',
            ['site_id' => $site->id, 'date' => $date, 'kind' => $kind, 'name' => $name, 'target_hash' => $targetHash, 'path_hash' => $pathHash],
            ['count' => 1],
            ['target' => $target, 'path' => $path],
            $visitorHash === null ? null : [$site->id, $date, 'event:'.hash('sha256', implode('|', [$kind, $name, $targetHash, $pathHash])), $visitorHash],
        );

        if ($kind === 'ai_fetch' || $visitorHash === null) {
            return;
        }

        $this->bump('daily_totals', ['site_id' => $site->id, 'date' => $date], ['events' => 1]);

        $visit = $this->openVisit($site, $visitorHash, $now);
        if ($visit !== null) {
            DB::table(Tables::name('visits'))
                ->where(['site_id' => $site->id, 'visitor_hash' => $visitorHash, 'started_at' => $visit->started_at])
                ->update(['last_seen_at' => $this->utc($now)]);
        }
    }

    /** Closes visits idle longer than visit_timeout_minutes (bounces, duration, exits). Returns the number closed. */
    public function closeIdleVisits(CarbonImmutable $now): int
    {
        $cutoff = $this->utc($now->subMinutes($this->timeoutMinutes()));
        $visits = DB::table(Tables::name('visits'))->where('last_seen_at', '<', $cutoff)->orderBy('last_seen_at')->get();

        $closed = 0;
        foreach ($visits as $visit) {
            $deleted = DB::table(Tables::name('visits'))
                ->where(['site_id' => $visit->site_id, 'visitor_hash' => $visit->visitor_hash, 'started_at' => $visit->started_at])
                ->delete();
            if ($deleted === 0) {
                continue; // closed by a concurrent run
            }
            $closed++;

            try {
                $this->countClosedVisit($visit);
            } catch (Throwable $e) {
                Log::warning('N2N Stats: failed to close a visit', ['exception' => $e::class]);
            }
        }

        return $closed;
    }

    /** @param  array{country: string, device: string, browser: string, os: string, language: string}  $dimensions */
    private function countNewVisit(Site $site, string $visitorHash, string $date, string $path, string $pathHash, Source $source, array $dimensions): void
    {
        $this->bump('daily_totals', ['site_id' => $site->id, 'date' => $date], ['visits' => 1]);
        $this->bump('daily_sources', ['site_id' => $site->id, 'date' => $date, 'source_key' => $source->key()], ['visits' => 1], $this->sourceFields($source));
        $this->bump('daily_pages', ['site_id' => $site->id, 'date' => $date, 'path_hash' => $pathHash], ['entries' => 1], ['path' => $path]);

        foreach ($dimensions as $dimension => $value) {
            $value = mb_substr($value, 0, 64);
            $this->bump(
                'daily_dimensions',
                ['site_id' => $site->id, 'date' => $date, 'dimension' => $dimension, 'value' => $value],
                ['visits' => 1],
                [],
                [$site->id, $date, 'dim:'.$dimension.':'.$value, $visitorHash],
            );
        }
    }

    private function countClosedVisit(stdClass $visit): void
    {
        $siteId = (int) $visit->site_id;
        $date = substr((string) $visit->date, 0, 10);
        $bounce = (int) $visit->pageviews === 1;
        $duration = max(0, CarbonImmutable::parse((string) $visit->started_at, 'UTC')->diffInSeconds(CarbonImmutable::parse((string) $visit->last_seen_at, 'UTC'), false));

        $totals = array_filter(['bounces' => $bounce ? 1 : 0, 'duration_seconds' => (int) $duration]);
        if ($totals !== []) {
            $this->bump('daily_totals', ['site_id' => $siteId, 'date' => $date], $totals);
        }

        if ($bounce) {
            $this->bump(
                'daily_sources',
                ['site_id' => $siteId, 'date' => $date, 'source_key' => (string) $visit->source_key],
                ['bounces' => 1],
                $this->knownSourceFields($siteId, (string) $visit->source_key),
            );
        }

        // A visit never spans two local dates (the visitor hash changes with the daily salt), so its pages are on its date.
        $this->retry(fn () => DB::table(Tables::name('daily_pages'))
            ->where(['site_id' => $siteId, 'date' => $date, 'path_hash' => (string) $visit->exit_path_hash])
            ->increment('exits'));
    }

    private function openVisit(Site $site, string $visitorHash, CarbonImmutable $now): ?stdClass
    {
        /** @var stdClass|null */
        return DB::table(Tables::name('visits'))
            ->where('site_id', $site->id)
            ->where('visitor_hash', $visitorHash)
            ->where('last_seen_at', '>=', $this->utc($now->subMinutes($this->timeoutMinutes())))
            ->orderByDesc('started_at')
            ->first();
    }

    /** @return array<string, string|null> */
    private function sourceFields(Source $source): array
    {
        return [
            'source_type' => $source->type,
            'source' => $source->source,
            'utm_medium' => $source->medium,
            'utm_campaign' => $source->campaign,
            'utm_content' => $source->content,
            'utm_term' => $source->term,
        ];
    }

    /**
     * Source columns of an open visit's source, in case its day row is missing (deleted by hand or by retention).
     *
     * @return array<string, string|null>
     */
    private function knownSourceFields(int $siteId, string $sourceKey): array
    {
        $row = DB::table(Tables::name('daily_sources'))
            ->where('site_id', $siteId)
            ->where('source_key', $sourceKey)
            ->orderByDesc('date')
            ->first(['source_type', 'source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']);

        return $row === null ? $this->sourceFields(Source::direct()) : (array) $row;
    }

    /**
     * Counter write (design §5.3): insertOrIgnore the uniques row to learn whether the visitor is new for the
     * scope, then UPDATE col = col + n; when no row was updated, insertOrIgnore the row and update again.
     * Deadlocks are retried; when the counter write fails, the uniques row inserted here is removed again.
     *
     * @param  array<string, mixed>  $keys  unique key columns of the row
     * @param  array<string, int>  $increments
     * @param  array<string, mixed>  $insert  other columns of a new row
     * @param  array{0: int, 1: string, 2: string, 3: string}|null  $unique  [site id, date, scope, visitor hash]
     */
    private function bump(string $table, array $keys, array $increments, array $insert = [], ?array $unique = null): void
    {
        $uniqueRow = null;
        if ($unique !== null) {
            $row = ['site_id' => $unique[0], 'date' => $unique[1], 'scope' => hash('sha256', $unique[2]), 'visitor_hash' => $unique[3]];
            if ($this->retry(fn () => DB::table(Tables::name('uniques'))->insertOrIgnore($row)) > 0) {
                $uniqueRow = $row;
                $increments['visitors'] = ($increments['visitors'] ?? 0) + 1;
            }
        }

        $table = Tables::name($table);
        try {
            // A transaction (a savepoint when the caller already has one) so a failed write is rolled back on its
            // own: PostgreSQL rejects every later statement of an aborted transaction, including the cleanup below.
            $this->retry(fn () => DB::transaction(function () use ($table, $keys, $increments, $insert): void {
                if (DB::table($table)->where($keys)->incrementEach($increments) === 0) {
                    [$keys, $insert] = $this->capped($table, $keys, $insert);
                    DB::table($table)->insertOrIgnore($keys + $insert);
                    DB::table($table)->where($keys)->incrementEach($increments);
                }
            }));
        } catch (Throwable $e) {
            if ($uniqueRow !== null) {
                DB::table(Tables::name('uniques'))->where($uniqueRow)->delete();
            }

            throw $e;
        }
    }

    /**
     * When a site already has max_new_rows_per_day rows in $table for the day, a new value is counted under
     * '(other)' instead, so forged paths, sources, events or dimension values cannot grow the tables without bound.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $insert
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function capped(string $table, array $keys, array $insert): array
    {
        $other = '(other)';
        $cap = (int) config('stats.max_new_rows_per_day', 5000);
        if ($table === Tables::name('daily_totals') || $cap <= 0) {
            return [$keys, $insert];
        }
        $rows = DB::table($table)->where('site_id', $keys['site_id'])->where('date', $keys['date'])->count();
        if ($rows < $cap) {
            return [$keys, $insert];
        }

        if ($table === Tables::name('daily_pages')) {
            return [['path_hash' => hash('sha256', $other)] + $keys, ['path' => $other]];
        }
        if ($table === Tables::name('daily_sources')) {
            $source = new Source('referral', $other);

            return [['source_key' => $source->key()] + $keys, $this->sourceFields($source)];
        }
        if ($table === Tables::name('daily_events')) {
            return [['name' => $other, 'target_hash' => hash('sha256', ''), 'path_hash' => hash('sha256', $other)] + $keys, ['target' => null, 'path' => $other]];
        }
        if ($table === Tables::name('daily_dimensions')) {
            return [['value' => $other] + $keys, $insert];
        }

        return [$keys, $insert];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function retry(callable $callback): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $callback();
            } catch (Throwable $e) {
                if ($attempt >= self::ATTEMPTS || ! $this->causedByConcurrencyError($e)) {
                    throw $e;
                }
                usleep(random_int(5_000, 50_000));
            }
        }
    }

    private function localDate(Site $site, CarbonImmutable $now): string
    {
        return $now->setTimezone($site->timezone ?: 'UTC')->toDateString();
    }

    private function utc(CarbonImmutable $time): string
    {
        return $time->utc()->format('Y-m-d H:i:s');
    }

    private function timeoutMinutes(): int
    {
        return max(1, (int) config('stats.visit_timeout_minutes', 30));
    }
}
