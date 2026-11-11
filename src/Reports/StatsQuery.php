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
 * "visitors" over a range is the sum of daily unique visitors: the same person on two days counts twice.
 */
class StatsQuery
{
    /** Row keys of each report, in order. The first numeric column is the report's main metric (its sort order). */
    public const COLUMNS = [
        'pages' => ['path', 'title', 'page_type', 'pageviews', 'visitors', 'entries', 'exits'],
        'entries' => ['path', 'title', 'entries', 'visitors', 'entry_pageviews', 'entry_outbound', 'entry_events'],
        'exits' => ['path', 'title', 'exits'],
        'page_types' => ['page_type', 'pageviews', 'visitors', 'pages'],
        'content' => ['content_group', 'title', 'pageviews', 'visitors', 'pages'],
        'transitions' => ['from_path', 'to_path', 'count', 'visitors'],
        'source_types' => ['source_type', 'visits', 'visitors', 'pageviews', 'outbound', 'events'],
        'sources' => ['source_type', 'source', 'utm_medium', 'visits', 'visitors', 'pageviews', 'bounce_rate', 'outbound', 'events'],
        'campaigns' => ['source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'visits', 'visitors', 'pageviews', 'outbound', 'events'],
        'landing_pages' => ['source_type', 'source', 'utm_campaign', 'path', 'visits', 'visitors'],
        'campaign_pages' => ['source', 'utm_campaign', 'path', 'visits', 'visitors'],
        'search_pages' => ['source', 'path', 'visits', 'visitors'],
        'countries' => ['value', 'visits', 'visitors', 'pageviews'],
        'devices' => ['value', 'visits', 'visitors', 'pageviews'],
        'browsers' => ['value', 'visits', 'visitors', 'pageviews'],
        'os' => ['value', 'visits', 'visitors', 'pageviews'],
        'languages' => ['value', 'visits', 'visitors', 'pageviews'],
        'locales' => ['value', 'visits', 'visitors', 'pageviews'],
        'outbound' => ['target', 'label', 'count', 'visitors'],
        'downloads' => ['target', 'label', 'count', 'visitors'],
        'events' => ['name', 'label', 'target', 'count', 'visitors'],
        'event_pages' => ['kind', 'name', 'label', 'target', 'path', 'count', 'visitors'],
        'ai_referrals' => ['source', 'visits', 'visitors', 'pageviews'],
        'ai_pages' => ['source', 'path', 'visits', 'visitors'],
        'ai_fetchers' => ['name', 'fetch_type', 'count', 'pages'],
        'ai_fetched_pages' => ['name', 'path', 'count'],
    ];

    public const REPORTS = [
        'pages', 'entries', 'exits', 'page_types', 'content', 'transitions', 'source_types', 'sources', 'campaigns',
        'landing_pages', 'campaign_pages', 'search_pages', 'countries', 'devices', 'browsers', 'os', 'languages',
        'locales', 'outbound', 'downloads', 'events', 'event_pages', 'ai_referrals', 'ai_pages', 'ai_fetchers',
        'ai_fetched_pages',
    ];

    private const DIMENSIONS = [
        'countries' => 'country', 'devices' => 'device', 'browsers' => 'browser', 'os' => 'os', 'languages' => 'language', 'locales' => 'locale',
    ];

    private const LANDING_TYPES = ['campaign_pages' => 'utm', 'search_pages' => 'search', 'ai_pages' => 'ai'];

    /**
     * Totals of the range, and the complete-day comparison (see Period::comparison()).
     *
     * @return array<string, mixed>
     */
    public function summary(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $period = new Period($from, $to);
        $today = Period::todayIn($site->timezone ?: 'UTC');
        [$current, $previous] = $period->comparison($today);

        return $this->totals($site, $from, $to) + [
            'partial' => $period->isPartial($today),
            'comparison' => [
                'current_from' => $current->from->toDateString(),
                'current_to' => $current->to->toDateString(),
                'previous_from' => $previous->from->toDateString(),
                'previous_to' => $previous->to->toDateString(),
                'current' => $current == $period ? $this->totals($site, $from, $to) : $this->totals($site, $current->from, $current->to),
                'previous' => $this->totals($site, $previous->from, $previous->to),
            ],
        ];
    }

    /** @return list<array{date: string, visitors: int, visits: int, pageviews: int, events: int, outbound: int}> one row per day, zero-filled */
    public function timeseries(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byDate = [];
        foreach ($this->range('daily_totals', $site, $from, $to)->get(['date', 'visitors', 'visits', 'pageviews', 'events', 'outbound']) as $row) {
            $byDate[substr((string) $row->date, 0, 10)] = $row;
        }

        $series = [];
        foreach ($this->days($from, $to) as $date) {
            $row = $byDate[$date] ?? null;
            $series[] = [
                'date' => $date,
                'visitors' => (int) ($row->visitors ?? 0),
                'visits' => (int) ($row->visits ?? 0),
                'pageviews' => (int) ($row->pageviews ?? 0),
                'events' => (int) ($row->events ?? 0),
                'outbound' => (int) ($row->outbound ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Visits referred by AI assistants and AI fetches: totals and one zero-filled row per day.
     *
     * @return array{visits: int, visitors: int, pageviews: int, fetches: int, series: list<array{date: string, visits: int, visitors: int, fetches: int}>}
     */
    public function ai(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $visits = [];
        $rows = $this->range('daily_sources', $site, $from, $to)->where('source_type', 'ai')
            ->select('date')->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews')
            ->groupBy('date')->get();
        foreach ($rows as $row) {
            $visits[substr((string) $row->date, 0, 10)] = $row;
        }
        $fetches = [];
        $rows = $this->range('daily_events', $site, $from, $to)->where('kind', 'ai_fetch')
            ->select('date')->selectRaw('SUM(count) AS count')->groupBy('date')->get();
        foreach ($rows as $row) {
            $fetches[substr((string) $row->date, 0, 10)] = (int) $row->count;
        }

        $result = ['visits' => 0, 'visitors' => 0, 'pageviews' => 0, 'fetches' => 0, 'series' => []];
        foreach ($this->days($from, $to) as $date) {
            $row = $visits[$date] ?? null;
            $day = ['date' => $date, 'visits' => (int) ($row->visits ?? 0), 'visitors' => (int) ($row->visitors ?? 0), 'fetches' => $fetches[$date] ?? 0];
            $result['visits'] += $day['visits'];
            $result['visitors'] += $day['visitors'];
            $result['pageviews'] += (int) ($row->pageviews ?? 0);
            $result['fetches'] += $day['fetches'];
            $result['series'][] = $day;
        }

        return $result;
    }

    /**
     * Rows of one report sorted by its main metric, ties broken by every grouped column so offset paging is
     * stable. $minVisitors drops rows with fewer visitors (reports that have
     * a visitors column), so small samples can be left out.
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function breakdown(Site $site, string $report, CarbonImmutable $from, CarbonImmutable $to, int $limit = 50, int $offset = 0, int $minVisitors = 0): array
    {
        if (! in_array($report, self::REPORTS, true)) {
            throw new InvalidArgumentException("Unknown report [{$report}].");
        }

        $query = $this->query($site, $report, $from, $to);
        if ($minVisitors > 0 && in_array('visitors', self::COLUMNS[$report], true)) {
            $query->havingRaw('SUM(visitors) >= ?', [$minVisitors]);
        }
        $rows = [];
        foreach ($query->offset(max(0, $offset))->limit(max(1, $limit))->get() as $row) {
            $rows[] = $this->row($report, (array) $row);
        }

        return $rows;
    }

    /** Sum of the report's main metric over all its rows (the base of each row's share). */
    public function total(Site $site, string $report, CarbonImmutable $from, CarbonImmutable $to): int
    {
        if (! in_array($report, self::REPORTS, true)) {
            throw new InvalidArgumentException("Unknown report [{$report}].");
        }

        return (int) DB::query()->fromSub($this->query($site, $report, $from, $to), 'r')->sum(self::mainMetric($report));
    }

    /** The column a report is sorted by. */
    public static function mainMetric(string $report): string
    {
        foreach (self::COLUMNS[$report] as $column) {
            if (in_array($column, ['pageviews', 'entries', 'exits', 'visits', 'count'], true)) {
                return $column;
            }
        }

        return 'count';
    }

    /** Visits active within the last 5 minutes. */
    public function realtimeVisitors(Site $site): int
    {
        return DB::table(Tables::name('visits'))
            ->where('site_id', $site->id)
            ->where('last_seen_at', '>=', now()->utc()->subMinutes(5)->format('Y-m-d H:i:s')) // stored in UTC
            ->count();
    }

    /**
     * All-time counts for public display: pageviews and the last day with a view per path, and custom event counts
     * per name and label.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $events
     * @return array{pages: array<string, array{pageviews: int, last_date: string}>, events: array<string, array<string, int>>}
     */
    public function publicCounts(Site $site, array $paths, array $events): array
    {
        $pages = [];
        if ($paths !== []) {
            $rows = DB::table(Tables::name('daily_pages'))->where('site_id', $site->id)
                ->whereIn('path_hash', array_map(fn (string $p): string => hash('sha256', $p), $paths))
                ->select('path')->selectRaw('SUM(pageviews) AS pageviews, MAX(date) AS last_date')
                ->groupBy('path')->get();
            foreach ($rows as $row) {
                $pages[(string) $row->path] = ['pageviews' => (int) $row->pageviews, 'last_date' => substr((string) $row->last_date, 0, 10)];
            }
        }

        $counts = [];
        if ($events !== []) {
            $rows = DB::table(Tables::name('daily_events'))->where('site_id', $site->id)->where('kind', 'custom')
                ->whereIn('name', $events)
                ->select('name', 'label')->selectRaw('SUM(count) AS count')
                ->groupBy('name', 'label')->get();
            foreach ($rows as $row) {
                $counts[(string) $row->name][(string) ($row->label ?? '')] = (int) $row->count;
            }
        }

        return ['pages' => $pages, 'events' => $counts];
    }

    private function query(Site $site, string $report, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        if (isset(self::DIMENSIONS[$report])) {
            return $this->range('daily_dimensions', $site, $from, $to)->where('dimension', self::DIMENSIONS[$report])
                ->select('value')->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews')
                ->groupBy('value')->orderByRaw('SUM(visits) DESC')->orderBy('value');
        }
        if (isset(self::LANDING_TYPES[$report])) {
            $columns = $report === 'campaign_pages' ? ['source', 'utm_campaign', 'path'] : ['source', 'path'];

            return $this->range('daily_entries', $site, $from, $to)->where('source_type', self::LANDING_TYPES[$report])
                ->select($columns)->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors')
                ->groupBy($columns)->orderByRaw('SUM(visits) DESC')->orderBy('path')->orderBy('source')
                ->when($report === 'campaign_pages', fn (Builder $q) => $q->orderBy('utm_campaign'));
        }

        $events = fn (string ...$kinds): Builder => $this->range('daily_events', $site, $from, $to)->whereIn('kind', $kinds);
        $sources = 'SUM(visits) AS visits, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews, SUM(outbound) AS outbound, SUM(events) AS events';

        return match ($report) {
            'pages' => $this->range('daily_pages', $site, $from, $to)->select('path')
                ->selectRaw('MAX(title) AS title, MAX(page_type) AS page_type, SUM(pageviews) AS pageviews, SUM(visitors) AS visitors, SUM(entries) AS entries, SUM(exits) AS exits')
                ->groupBy('path')->orderByRaw('SUM(pageviews) DESC')->orderBy('path'),
            'entries' => $this->range('daily_pages', $site, $from, $to)->where('entries', '>', 0)->select('path')
                ->selectRaw('MAX(title) AS title, SUM(entries) AS entries, SUM(visitors) AS visitors, SUM(entry_pageviews) AS entry_pageviews, SUM(entry_outbound) AS entry_outbound, SUM(entry_events) AS entry_events')
                ->groupBy('path')->orderByRaw('SUM(entries) DESC')->orderBy('path'),
            'exits' => $this->range('daily_pages', $site, $from, $to)->where('exits', '>', 0)->select('path')
                ->selectRaw('MAX(title) AS title, SUM(exits) AS exits')
                ->groupBy('path')->orderByRaw('SUM(exits) DESC')->orderBy('path'),
            'page_types' => $this->range('daily_pages', $site, $from, $to)->whereNotNull('page_type')->select('page_type')
                ->selectRaw('SUM(pageviews) AS pageviews, SUM(visitors) AS visitors, COUNT(DISTINCT path_hash) AS pages')
                ->groupBy('page_type')->orderByRaw('SUM(pageviews) DESC')->orderBy('page_type'),
            'content' => $this->range('daily_pages', $site, $from, $to)->whereNotNull('content_group')->select('content_group')
                ->selectRaw('MAX(title) AS title, SUM(pageviews) AS pageviews, SUM(visitors) AS visitors, COUNT(DISTINCT path_hash) AS pages')
                ->groupBy('content_group')->orderByRaw('SUM(pageviews) DESC')->orderBy('content_group'),
            'transitions' => $this->range('daily_transitions', $site, $from, $to)->select('from_path', 'to_path')
                ->selectRaw('SUM(count) AS count, SUM(visitors) AS visitors')
                ->groupBy('from_path', 'to_path')->orderByRaw('SUM(count) DESC')->orderBy('from_path')->orderBy('to_path'),
            'source_types' => $this->range('daily_sources', $site, $from, $to)->select('source_type')->selectRaw($sources)
                ->groupBy('source_type')->orderByRaw('SUM(visits) DESC')->orderBy('source_type'),
            'sources' => $this->range('daily_sources', $site, $from, $to)->select('source_type', 'source', 'utm_medium')
                ->selectRaw($sources.', SUM(bounces) AS bounces')
                ->groupBy('source_type', 'source', 'utm_medium')->orderByRaw('SUM(visits) DESC')->orderBy('source_type')->orderBy('source')->orderBy('utm_medium'),
            'campaigns' => $this->range('daily_sources', $site, $from, $to)->where('source_type', 'utm')
                ->select('source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term')->selectRaw($sources)
                ->groupBy('source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term')
                ->orderByRaw('SUM(visits) DESC')->orderBy('source')->orderBy('utm_campaign')->orderBy('utm_medium')->orderBy('utm_content')->orderBy('utm_term'),
            'landing_pages' => $this->range('daily_entries', $site, $from, $to)->select('source_type', 'source', 'utm_campaign', 'path')
                ->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors')
                ->groupBy('source_type', 'source', 'utm_campaign', 'path')->orderByRaw('SUM(visits) DESC')->orderBy('path')
                ->orderBy('source_type')->orderBy('source')->orderBy('utm_campaign'),
            'outbound', 'downloads' => $events($report === 'outbound' ? 'outbound' : 'download')
                ->select('target', 'label')->selectRaw('SUM(count) AS count, SUM(visitors) AS visitors')
                ->groupBy('target', 'label')->orderByRaw('SUM(count) DESC')->orderBy('target')->orderBy('label'),
            'events' => $events('custom')->select('name', 'label', 'target')->selectRaw('SUM(count) AS count, SUM(visitors) AS visitors')
                ->groupBy('name', 'label', 'target')->orderByRaw('SUM(count) DESC')->orderBy('name')->orderBy('label')->orderBy('target'),
            'event_pages' => $events('custom', 'outbound', 'download')->select('kind', 'name', 'label', 'target', 'path')
                ->selectRaw('SUM(count) AS count, SUM(visitors) AS visitors')
                ->groupBy('kind', 'name', 'label', 'target', 'path')->orderByRaw('SUM(count) DESC')->orderBy('path')->orderBy('name')
                ->orderBy('kind')->orderBy('label')->orderBy('target'),
            'ai_referrals' => $this->range('daily_sources', $site, $from, $to)->where('source_type', 'ai')->select('source')
                ->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews')
                ->groupBy('source')->orderByRaw('SUM(visits) DESC')->orderBy('source'),
            'ai_fetchers' => $events('ai_fetch')->select('name')->selectRaw('SUM(count) AS count, COUNT(DISTINCT path_hash) AS pages')
                ->groupBy('name')->orderByRaw('SUM(count) DESC')->orderBy('name'),
            default => $events('ai_fetch')->select('name', 'path')->selectRaw('SUM(count) AS count') // ai_fetched_pages
                ->groupBy('name', 'path')->orderByRaw('SUM(count) DESC')->orderBy('name')->orderBy('path'),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, int|float|string|null>
     */
    private function row(string $report, array $row): array
    {
        $out = [];
        foreach (self::COLUMNS[$report] as $column) {
            $out[$column] = match ($column) {
                'bounce_rate' => (int) $row['visits'] > 0 ? round((int) $row['bounces'] / (int) $row['visits'], 4) : 0.0,
                'fetch_type' => in_array((string) $row['name'], (array) config('stats.ai_user_fetchers', []), true) ? 'user' : 'crawler',
                'path', 'title', 'page_type', 'content_group', 'from_path', 'to_path', 'source_type', 'source', 'utm_medium',
                'utm_campaign', 'utm_content', 'utm_term', 'value', 'target', 'label', 'name', 'kind' => isset($row[$column]) ? (string) $row[$column] : null,
                default => (int) $row[$column],
            };
        }

        return $out;
    }

    /** @return array<string, int|float> */
    private function totals(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->range('daily_totals', $site, $from, $to)
            ->selectRaw('SUM(visitors) AS visitors, SUM(visits) AS visits, SUM(pageviews) AS pageviews, SUM(bounces) AS bounces, SUM(duration_seconds) AS duration_seconds, SUM(events) AS events, SUM(outbound) AS outbound')
            ->first();
        $pages = $this->range('daily_pages', $site, $from, $to)->where('pageviews', '>', 0)->distinct()->count('path_hash');

        $visits = (int) ($row->visits ?? 0);
        $pageviews = (int) ($row->pageviews ?? 0);

        return [
            'visitors' => (int) ($row->visitors ?? 0),
            'visits' => $visits,
            'pageviews' => $pageviews,
            'pages' => $pages,
            'views_per_visit' => $visits > 0 ? round($pageviews / $visits, 2) : 0.0,
            'bounce_rate' => $visits > 0 ? round((int) ($row->bounces ?? 0) / $visits, 4) : 0.0,
            'avg_duration_seconds' => intdiv((int) ($row->duration_seconds ?? 0), max($visits, 1)),
            'events' => (int) ($row->events ?? 0),
            'outbound' => (int) ($row->outbound ?? 0),
        ];
    }

    /** @return list<string> */
    private function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    private function range(string $table, Site $site, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table(Tables::name($table))
            ->where('site_id', $site->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }
}
