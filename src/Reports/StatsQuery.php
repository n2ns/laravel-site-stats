<?php

namespace N2ns\Stats\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\Source;
use N2ns\Stats\Support\Tables;

/**
 * Reads the daily aggregate tables for the dashboard, widgets and API. $from/$to are site-local dates, inclusive.
 *
 * "visitors" over a range is the sum of daily unique visitors: the same person on two days counts twice.
 */
class StatsQuery
{
    /** Row keys of each report, in order. The main metric (its sort order and share) is mainMetric(). */
    public const COLUMNS = [
        'pages' => ['path', 'title', 'page_type', 'pageviews', 'visitors', 'entries', 'exits'],
        'entries' => ['path', 'title', 'entries', 'visitors', 'entry_pageviews', 'entry_outbound', 'entry_events'],
        'exits' => ['path', 'title', 'exits'],
        'engagement' => [
            'path', 'title', 'engaged_views', 'avg_seconds', 'time_10', 'time_30', 'time_60', 'time_180',
            'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100',
        ],
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
        'event_locales' => ['locale', 'kind', 'name', 'count', 'visitors'],
        'ai_referrals' => ['source', 'visits', 'visitors', 'pageviews'],
        'ai_pages' => ['source', 'path', 'visits', 'visitors'],
        'ai_fetchers' => ['name', 'fetch_type', 'count', 'pages'],
        'ai_fetched_pages' => ['name', 'path', 'count'],
    ];

    public const REPORTS = [
        'pages', 'entries', 'exits', 'engagement', 'page_types', 'content', 'transitions', 'source_types', 'sources', 'campaigns',
        'landing_pages', 'campaign_pages', 'search_pages', 'countries', 'devices', 'browsers', 'os', 'languages',
        'locales', 'outbound', 'downloads', 'events', 'event_pages', 'event_locales', 'ai_referrals', 'ai_pages', 'ai_fetchers',
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

    /**
     * One zero-filled row per day: the counts, and the day's pages viewed, views per visit, bounce rate, average visit
     * duration and average time on page.
     *
     * @return list<array{date: string, visitors: int, visits: int, pageviews: int, events: int, outbound: int, pages: int, views_per_visit: float, bounce_rate: float, avg_duration_seconds: int, avg_page_seconds: int}>
     */
    public function timeseries(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byDate = [];
        foreach ($this->range('daily_totals', $site, $from, $to)->get(['date', 'visitors', 'visits', 'pageviews', 'events', 'outbound', 'bounces', 'duration_seconds']) as $row) {
            $byDate[substr((string) $row->date, 0, 10)] = $row;
        }
        $pagesByDate = [];
        $pageRows = $this->range('daily_pages', $site, $from, $to)->select('date')
            ->selectRaw('SUM(CASE WHEN pageviews > 0 THEN 1 ELSE 0 END) AS pages, SUM(engaged_views) AS engaged_views, SUM(engaged_seconds) AS engaged_seconds, SUM(exits) AS ended, SUM(entry_pageviews) AS ended_pageviews')
            ->groupBy('date')->get();
        foreach ($pageRows as $row) {
            $pagesByDate[substr((string) $row->date, 0, 10)] = $row;
        }

        $series = [];
        foreach ($this->days($from, $to) as $date) {
            $row = $byDate[$date] ?? null;
            $pages = $pagesByDate[$date] ?? null;
            $visits = (int) ($row->visits ?? 0);
            $pageviews = (int) ($row->pageviews ?? 0);
            $engagedViews = (int) ($pages->engaged_views ?? 0);
            $series[] = [
                'date' => $date,
                'visitors' => (int) ($row->visitors ?? 0),
                'visits' => $visits,
                'pageviews' => $pageviews,
                'events' => (int) ($row->events ?? 0),
                'outbound' => (int) ($row->outbound ?? 0),
                'pages' => (int) ($pages->pages ?? 0),
                'views_per_visit' => (int) ($pages->ended ?? 0) > 0 ? round((int) ($pages->ended_pageviews ?? 0) / (int) $pages->ended, 2) : 0.0,
                'bounce_rate' => $visits > 0 ? round((int) ($row->bounces ?? 0) / $visits, 4) : 0.0,
                'avg_duration_seconds' => intdiv((int) ($row->duration_seconds ?? 0), max($visits, 1)),
                'avg_page_seconds' => $engagedViews > 0 ? intdiv((int) ($pages->engaged_seconds ?? 0), $engagedViews) : 0,
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
     * Time on page and scroll depth of the whole range: the page views that reported them and the share of those that
     * reached each threshold.
     *
     * @return array{views: int, time_10: float, time_30: float, time_60: float, time_180: float, scroll_25: float, scroll_50: float, scroll_75: float, scroll_100: float}
     */
    public function engagementTotals(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $thresholds = ['time_10', 'time_30', 'time_60', 'time_180', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100'];
        $row = $this->range('daily_pages', $site, $from, $to)
            ->selectRaw(implode(', ', array_map(fn (string $c): string => "SUM({$c}) AS {$c}", ['engaged_views', ...$thresholds])))->first();
        $views = (int) ($row->engaged_views ?? 0);
        $out = ['views' => $views];
        foreach ($thresholds as $column) {
            $out[$column] = $views > 0 ? round((int) ($row->{$column} ?? 0) / $views, 4) : 0.0;
        }

        /** @var array{views: int, time_10: float, time_30: float, time_60: float, time_180: float, scroll_25: float, scroll_50: float, scroll_75: float, scroll_100: float} */
        return $out;
    }

    /**
     * Daily visitors per source type (the types with visitors in the range, in Source::TYPES order).
     *
     * @return array{dates: list<string>, series: array<string, list<int>>}
     */
    public function sourceTypeSeries(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->range('daily_sources', $site, $from, $to)->select('date', 'source_type')
            ->selectRaw('SUM(visitors) AS visitors')->groupBy('date', 'source_type')->get();
        $values = [];
        foreach ($rows as $row) {
            $values[(string) $row->source_type][substr((string) $row->date, 0, 10)] = (int) $row->visitors;
        }

        return $this->daily($from, $to, array_intersect_key(array_replace(array_fill_keys(Source::TYPES, null), $values), $values));
    }

    /**
     * Daily count of the custom events with the highest count in the range.
     *
     * @return array{dates: list<string>, series: array<string, list<int>>}
     */
    public function eventSeries(Site $site, CarbonImmutable $from, CarbonImmutable $to, int $top = 5): array
    {
        $names = $this->range('daily_events', $site, $from, $to)->where('kind', 'custom')->select('name')
            ->groupBy('name')->orderByRaw('SUM(count) DESC')->orderBy('name')->limit($top)->pluck('name')->map(fn ($n): string => (string) $n)->all();
        $values = array_fill_keys($names, []);
        if ($names !== []) {
            $rows = $this->range('daily_events', $site, $from, $to)->where('kind', 'custom')->whereIn('name', $names)
                ->select('date', 'name')->selectRaw('SUM(count) AS count')->groupBy('date', 'name')->get();
            foreach ($rows as $row) {
                $values[(string) $row->name][substr((string) $row->date, 0, 10)] = (int) $row->count;
            }
        }

        return $this->daily($from, $to, $values);
    }

    /**
     * The pages with the most custom events, with their page views and the events per page view.
     *
     * @return list<array{path: string, events: int, pageviews: int, rate: float}>
     */
    public function eventRates(Site $site, CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        $events = $this->range('daily_events', $site, $from, $to)->where('kind', 'custom')->whereNotNull('path')
            ->select('path_hash')->selectRaw('MAX(path) AS path, SUM(count) AS events')
            ->groupBy('path_hash')->orderByRaw('SUM(count) DESC')->orderBy('path_hash')->limit($limit)->get();
        $views = $this->range('daily_pages', $site, $from, $to)->whereIn('path_hash', $events->pluck('path_hash')->all())
            ->select('path_hash')->selectRaw('SUM(pageviews) AS pageviews')->groupBy('path_hash')->pluck('pageviews', 'path_hash');
        $rows = [];
        foreach ($events as $row) {
            $pageviews = (int) ($views[$row->path_hash] ?? 0);
            if ($pageviews > 0) {
                $rows[] = ['path' => (string) $row->path, 'events' => (int) $row->events, 'pageviews' => $pageviews, 'rate' => round((int) $row->events / $pageviews, 4)];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, int>|null>  $values  series => date => value
     * @return array{dates: list<string>, series: array<string, list<int>>}
     */
    private function daily(CarbonImmutable $from, CarbonImmutable $to, array $values): array
    {
        $dates = $this->days($from, $to);
        $series = [];
        foreach ($values as $name => $byDate) {
            $series[(string) $name] = array_map(fn (string $date): int => $byDate[$date] ?? 0, $dates);
        }

        return ['dates' => $dates, 'series' => $series];
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

        if ($report === 'event_locales') {
            $rows = array_filter($this->eventLocales($site, $from, $to), fn (array $row): bool => $row['visitors'] >= $minVisitors);

            return array_slice(array_values($rows), max(0, $offset), max(1, $limit));
        }

        $query = $this->query($site, $report, $from, $to);
        if ($minVisitors > 0 && in_array('visitors', self::COLUMNS[$report], true)) {
            $query->havingRaw('SUM('.($report === 'entries' ? 'entry_visitors' : 'visitors').') >= ?', [$minVisitors]);
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

        if ($report === 'event_locales') {
            return array_sum(array_column($this->eventLocales($site, $from, $to), 'count'));
        }

        return (int) DB::query()->fromSub($this->query($site, $report, $from, $to), 'r')->sum(self::mainMetric($report));
    }

    /**
     * The column a report is sorted by. Reports of visits use their visitors, which days without visit data (such as
     * imported history) also have.
     */
    public static function mainMetric(string $report): string
    {
        foreach (self::COLUMNS[$report] as $column) {
            if (in_array($column, ['pageviews', 'entries', 'exits', 'engaged_views', 'count'], true)) {
                return $column;
            }
            if ($column === 'visits') {
                return 'visitors';
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
                ->groupBy('value')->orderByRaw('SUM(visitors) DESC')->orderBy('value');
        }
        if (isset(self::LANDING_TYPES[$report])) {
            $columns = $report === 'campaign_pages' ? ['source', 'utm_campaign', 'path'] : ['source', 'path'];

            return $this->range('daily_entries', $site, $from, $to)->where('source_type', self::LANDING_TYPES[$report])
                ->select($columns)->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors')
                ->groupBy($columns)->orderByRaw('SUM(visitors) DESC')->orderBy('path')->orderBy('source')
                ->when($report === 'campaign_pages', fn (Builder $q) => $q->orderBy('utm_campaign'));
        }

        $events = fn (string ...$kinds): Builder => $this->range('daily_events', $site, $from, $to)->whereIn('kind', $kinds);
        $sources = 'SUM(visits) AS visits, SUM(visitors) AS visitors, SUM(pageviews) AS pageviews, SUM(outbound) AS outbound, SUM(events) AS events';

        return match ($report) {
            'pages' => $this->range('daily_pages', $site, $from, $to)->select('path')
                ->selectRaw('MAX(title) AS title, MAX(page_type) AS page_type, SUM(pageviews) AS pageviews, SUM(visitors) AS visitors, SUM(entries) AS entries, SUM(exits) AS exits')
                ->groupBy('path')->havingRaw('SUM(pageviews) > 0')->orderByRaw('SUM(pageviews) DESC')->orderBy('path'),
            'entries' => $this->range('daily_pages', $site, $from, $to)->where('entries', '>', 0)->select('path')
                ->selectRaw('MAX(title) AS title, SUM(entries) AS entries, SUM(entry_visitors) AS visitors, SUM(entry_pageviews) AS entry_pageviews, SUM(entry_outbound) AS entry_outbound, SUM(entry_events) AS entry_events')
                ->groupBy('path')->orderByRaw('SUM(entries) DESC')->orderBy('path'),
            // Summed per path first: a page view reported after midnight leaves seconds on a day without a measured view.
            'engagement' => $this->range('daily_pages', $site, $from, $to)->select('path')
                ->selectRaw('MAX(title) AS title, SUM(engaged_views) AS engaged_views, SUM(engaged_seconds) AS engaged_seconds, '
                    .'SUM(time_10) AS time_10, SUM(time_30) AS time_30, SUM(time_60) AS time_60, SUM(time_180) AS time_180, '
                    .'SUM(scroll_25) AS scroll_25, SUM(scroll_50) AS scroll_50, SUM(scroll_75) AS scroll_75, SUM(scroll_100) AS scroll_100')
                ->groupBy('path')->havingRaw('SUM(engaged_views) > 0')->orderByRaw('SUM(engaged_views) DESC')->orderBy('path'),
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
                ->groupBy('source_type')->orderByRaw('SUM(visitors) DESC')->orderBy('source_type'),
            'sources' => $this->range('daily_sources', $site, $from, $to)->select('source_type', 'source', 'utm_medium')
                ->selectRaw($sources.', SUM(bounces) AS bounces')
                ->groupBy('source_type', 'source', 'utm_medium')->orderByRaw('SUM(visitors) DESC')->orderBy('source_type')->orderBy('source')->orderBy('utm_medium'),
            'campaigns' => $this->range('daily_sources', $site, $from, $to)->where('source_type', 'utm')
                ->select('source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term')->selectRaw($sources)
                ->groupBy('source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term')
                ->orderByRaw('SUM(visitors) DESC')->orderBy('source')->orderBy('utm_campaign')->orderBy('utm_medium')->orderBy('utm_content')->orderBy('utm_term'),
            'landing_pages' => $this->range('daily_entries', $site, $from, $to)->select('source_type', 'source', 'utm_campaign', 'path')
                ->selectRaw('SUM(visits) AS visits, SUM(visitors) AS visitors')
                ->groupBy('source_type', 'source', 'utm_campaign', 'path')->orderByRaw('SUM(visitors) DESC')->orderBy('path')
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
                ->groupBy('source')->orderByRaw('SUM(visitors) DESC')->orderBy('source'),
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
        $views = (int) ($row['engaged_views'] ?? 0);
        foreach (self::COLUMNS[$report] as $column) {
            $out[$column] = match ($column) {
                'avg_seconds' => $views > 0 ? intdiv((int) $row['engaged_seconds'], $views) : 0,
                'time_10', 'time_30', 'time_60', 'time_180', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100' => $views > 0 ? round((int) $row[$column] / $views, 4) : 0.0,
                'bounce_rate' => (int) $row['visits'] > 0 ? round((int) $row['bounces'] / (int) $row['visits'], 4) : null, // no visits recorded, such as imported history
                'fetch_type' => in_array((string) $row['name'], (array) config('stats.ai_user_fetchers', []), true) ? 'user' : 'crawler',
                'path', 'title', 'page_type', 'content_group', 'from_path', 'to_path', 'source_type', 'source', 'utm_medium',
                'utm_campaign', 'utm_content', 'utm_term', 'value', 'target', 'label', 'name', 'kind', 'locale' => isset($row[$column]) ? (string) $row[$column] : null,
                default => (int) $row[$column],
            };
        }

        return $out;
    }

    /**
     * Outbound clicks, downloads and custom events per site language version of the page they happened on (from the
     * page path and the site's locales). Empty for sites without locales. "visitors" is the sum over the pages.
     *
     * @return list<array{locale: string, kind: string, name: string, count: int, visitors: int}>
     */
    private function eventLocales(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        $events = $this->range('daily_events', $site, $from, $to)->whereIn('kind', ['custom', 'outbound', 'download'])
            ->select('kind', 'name', 'path')->selectRaw('SUM(count) AS count, SUM(visitors) AS visitors')
            ->groupBy('kind', 'name', 'path')->get();
        foreach ($events as $event) {
            $locale = $site->localeOf((string) $event->path);
            if ($locale === null) {
                continue;
            }
            $key = $locale."\0".$event->kind."\0".$event->name;
            $rows[$key] ??= ['locale' => $locale, 'kind' => (string) $event->kind, 'name' => (string) $event->name, 'count' => 0, 'visitors' => 0];
            $rows[$key]['count'] += (int) $event->count;
            $rows[$key]['visitors'] += (int) $event->visitors;
        }
        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b): int => [$b['count'], $a['locale'], $a['kind'], $a['name']] <=> [$a['count'], $b['locale'], $b['kind'], $b['name']]);

        return $rows;
    }

    /** @return array<string, int|float> */
    private function totals(Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->range('daily_totals', $site, $from, $to)
            ->selectRaw('SUM(visitors) AS visitors, SUM(visits) AS visits, SUM(pageviews) AS pageviews, SUM(bounces) AS bounces, SUM(duration_seconds) AS duration_seconds, SUM(events) AS events, SUM(outbound) AS outbound')
            ->first();
        $pages = $this->range('daily_pages', $site, $from, $to)->where('pageviews', '>', 0)->distinct()->count('path_hash');
        // Ended visits (one exit each) and their page views (added to their entry page): only visits recorded here, so
        // days with page views but no visit data (imported history, or the part of a day before counting began) are left out.
        $engaged = $this->range('daily_pages', $site, $from, $to)
            ->selectRaw('SUM(engaged_views) AS v, SUM(engaged_seconds) AS s, SUM(exits) AS ended, SUM(entry_pageviews) AS ended_pageviews')->first();
        $ended = (int) ($engaged->ended ?? 0);
        $engagedViews = (int) ($engaged->v ?? 0);

        $visits = (int) ($row->visits ?? 0);
        $pageviews = (int) ($row->pageviews ?? 0);

        return [
            'visitors' => (int) ($row->visitors ?? 0),
            'visits' => $visits,
            'pageviews' => $pageviews,
            'pages' => $pages,
            'views_per_visit' => $ended > 0 ? round((int) ($engaged->ended_pageviews ?? 0) / $ended, 2) : 0.0,
            'bounce_rate' => $visits > 0 ? round((int) ($row->bounces ?? 0) / $visits, 4) : 0.0,
            'avg_duration_seconds' => intdiv((int) ($row->duration_seconds ?? 0), max($visits, 1)),
            'avg_page_seconds' => $engagedViews > 0 ? intdiv((int) ($engaged->s ?? 0), $engagedViews) : 0,
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
