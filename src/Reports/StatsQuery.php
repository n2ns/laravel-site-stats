<?php

namespace N2ns\Stats\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\Tables;

/**
 * Reads the daily aggregate tables for the dashboard, widgets and API. $from/$to are site-local dates, inclusive.
 *
 * Report names for breakdown(): pages, entries, exits, sources, source_types, campaigns, countries, devices,
 * browsers, os, languages, outbound, downloads, events, ai.
 *
 * "visitors" over a range is the sum of daily unique visitors: the same person on two days counts twice.
 */
class StatsQuery
{
    public const REPORTS = [
        'pages', 'entries', 'exits', 'sources', 'source_types', 'campaigns', 'countries', 'devices',
        'browsers', 'os', 'languages', 'outbound', 'downloads', 'events', 'ai',
    ];

    /** Row keys of each report, in order. */
    public const COLUMNS = [
        'pages' => ['path', 'pageviews', 'visitors', 'entries', 'exits'],
        'entries' => ['path', 'entries', 'visitors'],
        'exits' => ['path', 'exits'],
        'sources' => ['source_type', 'source', 'visits', 'visitors', 'pageviews', 'bounce_rate'],
        'source_types' => ['source_type', 'visits', 'visitors'],
        'campaigns' => ['source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'visits', 'visitors'],
        'countries' => ['value', 'visits', 'visitors'],
        'devices' => ['value', 'visits', 'visitors'],
        'browsers' => ['value', 'visits', 'visitors'],
        'os' => ['value', 'visits', 'visitors'],
        'languages' => ['value', 'visits', 'visitors'],
        'outbound' => ['target', 'count', 'visitors'],
        'downloads' => ['target', 'count', 'visitors'],
        'events' => ['name', 'label', 'count', 'visitors'],
        'ai' => ['kind', 'name', 'path', 'count'],
    ];

    private const DIMENSIONS = [
        'countries' => 'country', 'devices' => 'device', 'browsers' => 'browser', 'os' => 'os', 'languages' => 'language',
    ];

    /**
     * @return array{visitors: int, visits: int, pageviews: int, views_per_visit: float, bounce_rate: float, avg_duration_seconds: int, events: int, previous: array{visitors: int, visits: int, pageviews: int, views_per_visit: float, bounce_rate: float, avg_duration_seconds: int, events: int}}
     */
    public function summary(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $previous = (new Period($from, $to))->previous();

        return $this->totals($site, $from, $to) + ['previous' => $this->totals($site, $previous->from, $previous->to)];
    }

    /** @return list<array{date: string, visitors: int, visits: int, pageviews: int}> one row per day, zero-filled */
    public function timeseries(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byDate = [];
        foreach ($this->range('daily_totals', $site, $from, $to)->get(['date', 'visitors', 'visits', 'pageviews']) as $row) {
            $byDate[substr((string) $row->date, 0, 10)] = $row;
        }

        $series = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $row = $byDate[$date] ?? null;
            $series[] = [
                'date' => $date,
                'visitors' => (int) ($row->visitors ?? 0),
                'visits' => (int) ($row->visits ?? 0),
                'pageviews' => (int) ($row->pageviews ?? 0),
            ];
        }

        return $series;
    }

    /** @return list<array<string, int|float|string|null>> rows sorted by the report's main metric, at most $limit */
    public function breakdown(Site $site, string $report, CarbonImmutable $from, CarbonImmutable $to, int $limit = 50): array
    {
        if (! in_array($report, self::REPORTS, true)) {
            throw new InvalidArgumentException("Unknown report [{$report}].");
        }
        $limit = max(1, $limit);

        if (isset(self::DIMENSIONS[$report])) {
            return $this->dimension($site, self::DIMENSIONS[$report], $from, $to, $limit);
        }

        return match ($report) {
            'pages' => $this->rows(
                $this->range('daily_pages', $site, $from, $to)->select('path')
                    ->selectRaw('SUM(pageviews) AS pageviews, SUM(visitors) AS visitors, SUM(entries) AS entries, SUM(exits) AS exits')
                    ->groupBy('path')->orderByRaw('SUM(pageviews) DESC')->orderBy('path'),
                $limit, ['pageviews', 'visitors', 'entries', 'exits'],
            ),
            'entries' => $this->rows(
                $this->range('daily_pages', $site, $from, $to)->where('entries', '>', 0)->select('path')
                    ->selectRaw('SUM(entries) AS entries, SUM(visitors) AS visitors')
                    ->groupBy('path')->orderByRaw('SUM(entries) DESC')->orderBy('path'),
                $limit, ['entries', 'visitors'],
            ),
            'exits' => $this->rows(
                $this->range('daily_pages', $site, $from, $to)->where('exits', '>', 0)->select('path')
                    ->selectRaw('SUM(exits) AS exits')
                    ->groupBy('path')->orderByRaw('SUM(exits) DESC')->orderBy('path'),
                $limit, ['exits'],
            ),
            'sources' => array_map(
                fn (array $row): array => $this->withBounceRate($row),
                $this->rows(
                    $this->range('daily_sources', $site, $from, $to)->select('source_type', 'source')
                        ->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews, SUM(bounces) AS bounces')
                        ->groupBy('source_type', 'source')->orderByRaw('SUM(visits) DESC')->orderBy('source_type')->orderBy('source'),
                    $limit, ['visits', 'visitors', 'pageviews', 'bounces'],
                ),
            ),
            'source_types' => $this->rows(
                $this->range('daily_sources', $site, $from, $to)->select('source_type')
                    ->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors')
                    ->groupBy('source_type')->orderByRaw('SUM(visits) DESC')->orderBy('source_type'),
                $limit, ['visits', 'visitors'],
            ),
            'campaigns' => $this->rows(
                $this->range('daily_sources', $site, $from, $to)->where('source_type', 'utm')
                    ->select('source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term')
                    ->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors')
                    ->groupBy('source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term')
                    ->orderByRaw('SUM(visits) DESC')->orderBy('source')->orderBy('utm_campaign'),
                $limit, ['visits', 'visitors'],
            ),
            'outbound', 'downloads' => $this->rows(
                $this->range('daily_events', $site, $from, $to)->where('kind', $report === 'outbound' ? 'outbound' : 'download')
                    ->select('target')->selectRaw('SUM(count) AS count, SUM(visitors) AS visitors')
                    ->groupBy('target')->orderByRaw('SUM(count) DESC')->orderBy('target'),
                $limit, ['count', 'visitors'],
            ),
            'events' => $this->rows(
                $this->range('daily_events', $site, $from, $to)->where('kind', 'custom')
                    ->select('name', 'target as label')->selectRaw('SUM(count) AS count, SUM(visitors) AS visitors')
                    ->groupBy('name', 'target')->orderByRaw('SUM(count) DESC')->orderBy('name')->orderBy('target'),
                $limit, ['count', 'visitors'],
            ),
            default => $this->ai($site, $from, $to, $limit),
        };
    }

    /** Visits active within the last 5 minutes. */
    public function realtimeVisitors(Site $site): int
    {
        return DB::table(Tables::name('visits'))
            ->where('site_id', $site->id)
            ->where('last_seen_at', '>=', now()->utc()->subMinutes(5)->format('Y-m-d H:i:s')) // stored in UTC
            ->count();
    }

    /** @return array{visitors: int, visits: int, pageviews: int, views_per_visit: float, bounce_rate: float, avg_duration_seconds: int, events: int} */
    private function totals(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->range('daily_totals', $site, $from, $to)
            ->selectRaw('SUM(visitors) AS visitors, SUM(visits) AS visits, SUM(pageviews) AS pageviews, SUM(bounces) AS bounces, SUM(duration_seconds) AS duration_seconds, SUM(events) AS events')
            ->first();

        $visits = (int) ($row->visits ?? 0);
        $pageviews = (int) ($row->pageviews ?? 0);

        return [
            'visitors' => (int) ($row->visitors ?? 0),
            'visits' => $visits,
            'pageviews' => $pageviews,
            'views_per_visit' => $visits > 0 ? round($pageviews / $visits, 2) : 0.0,
            'bounce_rate' => $visits > 0 ? round((int) ($row->bounces ?? 0) / $visits, 4) : 0.0,
            'avg_duration_seconds' => intdiv((int) ($row->duration_seconds ?? 0), max($visits, 1)),
            'events' => (int) ($row->events ?? 0),
        ];
    }

    private function range(string $table, Site $site, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table(Tables::name($table))
            ->where('site_id', $site->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @param  list<string>  $ints  columns to cast to int
     * @return list<array<string, int|float|string|null>>
     */
    private function rows(Builder $query, int $limit, array $ints): array
    {
        $rows = [];
        foreach ($query->limit($limit)->get() as $row) {
            $row = (array) $row;
            foreach ($ints as $column) {
                $row[$column] = (int) $row[$column];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, int|float|string|null>  $row
     * @return array<string, int|float|string|null>
     */
    private function withBounceRate(array $row): array
    {
        $visits = (int) $row['visits'];
        $row['bounce_rate'] = $visits > 0 ? round((int) $row['bounces'] / $visits, 4) : 0.0;
        unset($row['bounces']);

        return $row;
    }

    /** @return list<array<string, int|float|string|null>> */
    private function dimension(Site $site, string $dimension, CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        return $this->rows(
            $this->range('daily_dimensions', $site, $from, $to)->where('dimension', $dimension)
                ->select('value')->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors')
                ->groupBy('value')->orderByRaw('SUM(visits) DESC')->orderBy('value'),
            $limit, ['visits', 'visitors'],
        );
    }

    /**
     * AI referral visits (sources of type ai) and AI fetches (ai_fetch events) in one list.
     *
     * @return list<array<string, int|float|string|null>>
     */
    private function ai(Site $site, CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $rows = [];
        $referrals = $this->range('daily_sources', $site, $from, $to)->where('source_type', 'ai')
            ->select('source')->selectRaw('SUM(visits) AS count')
            ->groupBy('source')->orderByRaw('SUM(visits) DESC')->orderBy('source')->limit($limit)->get();
        foreach ($referrals as $row) {
            $rows[] = ['kind' => 'referral', 'name' => $row->source, 'path' => null, 'count' => (int) $row->count];
        }

        $fetches = $this->range('daily_events', $site, $from, $to)->where('kind', 'ai_fetch')
            ->select('name', 'path')->selectRaw('SUM(count) AS count')
            ->groupBy('name', 'path')->orderByRaw('SUM(count) DESC')->orderBy('name')->orderBy('path')->limit($limit)->get();
        foreach ($fetches as $row) {
            $rows[] = ['kind' => 'fetch', 'name' => $row->name, 'path' => $row->path, 'count' => (int) $row->count];
        }

        usort($rows, fn (array $a, array $b): int => [$b['count'], $a['kind'], $a['name'], $a['path']] <=> [$a['count'], $b['kind'], $b['name'], $b['path']]);

        return array_slice($rows, 0, $limit);
    }
}
