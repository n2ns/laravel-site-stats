<?php

namespace N2ns\Stats\Http\View;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\StatsQuery;

/**
 * The charts of the report tabs, shared by the standalone dashboard and the Filament page (inline SVG and HTML,
 * partials/charts/*). Each chart is built for one site and range by build().
 */
final class Charts
{
    /** Charts shown above the report tables of each tab. */
    public const TABS = [
        'pages' => ['time_on_page', 'scroll_depth', 'content_groups', 'page_flow'],
        'sources' => ['source_trend', 'source_bounce'],
        'visitors' => ['world_map'],
        'events' => ['event_trend', 'event_rates'],
        'ai' => ['ai_fetchers'],
    ];

    /** Charts that take the full width of the tab. */
    public const WIDE = ['page_flow', 'source_trend', 'world_map', 'event_trend'];

    /** Rows of the bar charts and moves of the page flow. */
    private const TOP = 10;

    /**
     * @return array{name: string, type: string, title: string, description: string, empty: bool, wide: bool}&array<string, mixed>
     */
    public static function build(StatsQuery $stats, Site $site, CarbonImmutable $from, CarbonImmutable $to, string $name): array
    {
        $data = match ($name) {
            'time_on_page', 'scroll_depth' => self::engagement($stats->engagementTotals($site, $from, $to), $name),
            'content_groups' => self::counts($stats->breakdown($site, 'content', $from, $to, self::TOP), 'content', 'pageviews'),
            'page_flow' => ['type' => 'flow', 'flow' => FlowChart::build($stats->breakdown($site, 'transitions', $from, $to, self::TOP))],
            'source_trend' => self::series($stats->sourceTypeSeries($site, $from, $to), true, fn (string $type): string => Dashboard::text('source_types', 'source_type', $type)),
            'source_bounce' => self::bounce($stats->breakdown($site, 'sources', $from, $to, 100)),
            'world_map' => self::map($stats->breakdown($site, 'countries', $from, $to, 500)),
            'event_trend' => self::series($stats->eventSeries($site, $from, $to), false, fn (string $event): string => $event),
            'event_rates' => self::rates($stats->eventRates($site, $from, $to, self::TOP)),
            'ai_fetchers' => self::counts($stats->breakdown($site, 'ai_fetchers', $from, $to, self::TOP), 'ai_fetchers', 'count'),
            default => throw new InvalidArgumentException("Unknown chart [{$name}]."),
        };
        $empty = match ($data['type']) {
            'bars' => $data['rows'] === [],
            'flow' => $data['flow']['links'] === [],
            'series' => $data['chart']['layers'] === [] || array_sum(array_column($data['chart']['layers'], 'total')) === 0,
            default => $data['map']['max'] === 0,
        };

        return $data + [
            'name' => $name,
            'title' => (string) __('stats::dashboard.charts.'.$name.'.title'),
            'description' => (string) __('stats::dashboard.charts.'.$name.'.description', $data['replace'] ?? []),
            'empty' => $empty,
            'wide' => in_array($name, self::WIDE, true),
        ];
    }

    /**
     * Every chart of every tab.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function all(StatsQuery $stats, Site $site, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        foreach (self::TABS as $tab => $names) {
            foreach ($names as $name) {
                $out[$tab][] = self::build($stats, $site, $from, $to, $name);
            }
        }

        return $out;
    }

    /**
     * Share of the measured page views reaching each time or scroll threshold.
     *
     * @param  array<string, int|float>  $totals  StatsQuery::engagementTotals()
     * @return array<string, mixed>
     */
    private static function engagement(array $totals, string $name): array
    {
        $columns = $name === 'time_on_page' ? ['time_10', 'time_30', 'time_60', 'time_180'] : ['scroll_25', 'scroll_50', 'scroll_75', 'scroll_100'];
        $rows = [];
        if ($totals['views'] > 0) {
            foreach ($columns as $column) {
                $rate = (float) $totals[$column];
                $rows[] = ['label' => Dashboard::header('engagement', $column), 'sub' => null, 'width' => round($rate * 100, 1), 'value' => Format::percent($rate)];
            }
        }

        return ['type' => 'bars', 'rows' => $rows, 'replace' => ['views' => Format::number((int) $totals['views'])]];
    }

    /**
     * The top rows of a report by a count, as bars relative to the largest.
     *
     * @param  list<array<string, int|float|string|null>>  $rows
     * @return array<string, mixed>
     */
    private static function counts(array $rows, string $report, string $metric): array
    {
        $max = $rows === [] ? 0 : max(array_map(fn (array $r): int => (int) $r[$metric], $rows));
        $bars = [];
        foreach ($rows as $row) {
            [$label, $sub] = match ($report) {
                'content' => [filled($row['title']) ? (string) $row['title'] : (string) $row['content_group'], filled($row['title']) ? (string) $row['content_group'] : null],
                default => [(string) $row['name'], Dashboard::text($report, 'fetch_type', (string) $row['fetch_type'])],
            };
            $value = (int) $row[$metric];
            $bars[] = ['label' => $label, 'sub' => $sub, 'width' => $max > 0 ? round($value / $max * 100, 1) : 0.0, 'value' => Format::number($value)];
        }

        return ['type' => 'bars', 'rows' => $bars];
    }

    /**
     * Bounce rate of the sources with the most visits (sources without visits have no bounce rate).
     *
     * @param  list<array<string, int|float|string|null>>  $rows  the sources report
     * @return array<string, mixed>
     */
    private static function bounce(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $r): bool => $r['bounce_rate'] !== null));
        usort($rows, fn (array $a, array $b): int => (int) $b['visits'] <=> (int) $a['visits']);
        $bars = [];
        foreach (array_slice($rows, 0, self::TOP) as $row) {
            $type = Dashboard::text('sources', 'source_type', (string) $row['source_type']);
            $rate = (float) $row['bounce_rate'];
            $bars[] = [
                'label' => $row['source'] === null ? $type : $type.' · '.$row['source'],
                'sub' => (string) __('stats::dashboard.charts.source_bounce.visits', ['count' => Format::number((int) $row['visits'])]),
                'width' => round($rate * 100, 1),
                'value' => Format::percent($rate),
            ];
        }

        return ['type' => 'bars', 'rows' => $bars];
    }

    /**
     * Custom events per page view of the pages with the most events, as bars relative to the highest rate.
     *
     * @param  list<array{path: string, events: int, pageviews: int, rate: float}>  $rows
     * @return array<string, mixed>
     */
    private static function rates(array $rows): array
    {
        $max = $rows === [] ? 0.0 : max(array_column($rows, 'rate'));
        $bars = [];
        foreach ($rows as $row) {
            $bars[] = [
                'label' => $row['path'],
                'sub' => (string) __('stats::dashboard.charts.event_rates.counts', ['events' => Format::number($row['events']), 'pageviews' => Format::number($row['pageviews'])]),
                'width' => $max > 0 ? round($row['rate'] / $max * 100, 1) : 0.0,
                'value' => Format::percent($row['rate']),
            ];
        }

        return ['type' => 'bars', 'rows' => $bars];
    }

    /**
     * @param  array{dates: list<string>, series: array<string, list<int>>}  $data
     * @param  callable(string): string  $label
     * @return array<string, mixed>
     */
    private static function series(array $data, bool $stacked, callable $label): array
    {
        $chart = SeriesChart::build($data['dates'], $data['series'], $stacked);
        foreach ($chart['layers'] as $i => $layer) {
            $chart['layers'][$i]['label'] = $label($layer['name']);
        }

        return ['type' => 'series', 'stacked' => $stacked, 'chart' => $chart, 'dates' => $data['dates'], 'series' => $data['series']];
    }

    /**
     * @param  list<array<string, int|float|string|null>>  $rows  the countries report
     * @return array<string, mixed>
     */
    private static function map(array $rows): array
    {
        $visitors = [];
        foreach ($rows as $row) {
            $code = strtoupper((string) $row['value']);
            if (preg_match('/^[A-Z]{2}$/', $code) === 1) {
                $visitors[$code] = ($visitors[$code] ?? 0) + (int) $row['visitors'];
            }
        }
        $map = WorldMap::build($visitors);

        return ['type' => 'map', 'map' => $map, 'replace' => ['max' => Format::number($map['max'])]];
    }
}
