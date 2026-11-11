<?php

namespace N2ns\Stats\Http\View;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\Period;
use N2ns\Stats\Reports\StatsQuery;

/** View data of the dashboard, shared by the standalone dashboard and the Filament page. */
final class Dashboard
{
    /** Report tabs, each a group of report tables; together they cover every report in StatsQuery::REPORTS. */
    public const TABS = [
        'pages' => ['pages', 'entries', 'exits', 'engagement', 'page_types', 'content', 'transitions'],
        'sources' => ['source_types', 'sources', 'campaigns', 'landing_pages', 'campaign_pages', 'search_pages'],
        'visitors' => ['countries', 'devices', 'browsers', 'os', 'languages', 'locales'],
        'events' => ['events', 'outbound', 'downloads', 'event_pages', 'event_locales'],
        'ai' => ['ai_referrals', 'ai_pages', 'ai_fetchers', 'ai_fetched_pages'],
    ];

    /** Reports that also get a donut chart of their main metric. */
    public const DONUTS = ['source_types', 'countries', 'devices', 'locales', 'page_types'];

    /** Metrics of the daily chart and its data table. */
    public const CHART_METRICS = ['visitors', 'visits', 'pageviews', 'events', 'outbound'];

    /** Metrics of the daily AI chart. */
    public const AI_CHART_METRICS = ['visits', 'visitors', 'fetches'];

    /** Rows a report card shows before "show all". */
    public const TOP_ROWS = 10;

    /**
     * Summary metrics: [format, lower is better, primary]. Primary metrics lead the KPI strip;
     * metrics with a daily series get a sparkline (see kpis()).
     */
    public const KPIS = [
        'visitors' => ['number', false, true],
        'visits' => ['number', false, true],
        'pageviews' => ['number', false, true],
        'bounce_rate' => ['percent', true, true],
        'avg_duration_seconds' => ['duration', false, true],
        'pages' => ['number', false, false],
        'views_per_visit' => ['decimal', false, false],
        'avg_page_seconds' => ['duration', false, false],
        'events' => ['number', false, false],
        'outbound' => ['number', false, false],
    ];

    /** Report columns that hold the share of page views reaching a time or scroll threshold. */
    private const RATE_COLUMNS = ['time_10', 'time_30', 'time_60', 'time_180', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100'];

    public static function isRate(string $column): bool
    {
        return in_array($column, self::RATE_COLUMNS, true);
    }

    /** Report columns whose values are text (everything else is a number). */
    private const TEXT_COLUMNS = [
        'path', 'title', 'page_type', 'content_group', 'from_path', 'to_path', 'source_type', 'source', 'utm_medium',
        'utm_campaign', 'utm_content', 'utm_term', 'value', 'target', 'label', 'name', 'kind', 'fetch_type',
        'locale',
    ];

    /**
     * The selected range; an invalid custom range falls back to the last 30 days with an error message.
     *
     * @return array{0: string, 1: Period, 2: string|null}
     */
    public static function period(Site $site, string $range, string $from, string $to): array
    {
        if ($range === 'custom') {
            try {
                return ['custom', Period::parse($from, $to), null];
            } catch (InvalidArgumentException) {
                return ['30d', Period::preset('30d', $site->timezone), __('stats::dashboard.range_invalid', ['max' => Period::MAX_DAYS])];
            }
        }

        if (! in_array($range, Period::PRESETS, true)) {
            $range = '30d';
        }

        return [$range, Period::preset($range, $site->timezone), null];
    }

    /**
     * Everything the dashboard partial shows for one site and range.
     *
     * @return array<string, mixed>
     */
    public static function data(StatsQuery $stats, Site $site, string $range, string $from, string $to): array
    {
        [$range, $period, $error] = self::period($site, $range, $from, $to);

        $reports = [];
        $totals = [];
        foreach (self::TABS as $names) {
            foreach ($names as $report) {
                $reports[$report] = $stats->breakdown($site, $report, $period->from, $period->to);
                $totals[$report] = $reports[$report] === [] ? 0 : $stats->total($site, $report, $period->from, $period->to);
            }
        }

        $summary = $stats->summary($site, $period->from, $period->to);
        $series = $stats->timeseries($site, $period->from, $period->to);

        return [
            'site' => $site,
            'range' => $range,
            'period' => $period,
            'rangeError' => $error,
            'summary' => $summary,
            'series' => $series,
            'kpis' => self::kpis($summary, $series),
            'ai' => $stats->ai($site, $period->from, $period->to),
            'realtime' => $stats->realtimeVisitors($site),
            'tabs' => self::TABS,
            'reports' => $reports,
            'totals' => $totals,
            'query' => $range === 'custom'
                ? ['range' => 'custom', 'from' => $period->from->toDateString(), 'to' => $period->to->toDateString()]
                : ['range' => $range],
        ];
    }

    /**
     * The KPI strip: formatted value, change over complete days with its tone, and a sparkline of the daily series.
     *
     * @param  array<string, mixed>  $summary  StatsQuery::summary()
     * @param  list<array<string, int|float|string>>  $series  StatsQuery::timeseries()
     * @return list<array{key: string, primary: bool, value: string, current: string, previous: string, change: float|null, delta: string, direction: string, tone: string, spark: string|null}>
     */
    public static function kpis(array $summary, array $series): array
    {
        /** @var array{current: array<string, int|float>, previous: array<string, int|float>} $comparison */
        $comparison = $summary['comparison'];
        $out = [];
        foreach (self::KPIS as $key => [$format, $lowerIsBetter, $primary]) {
            $current = $comparison['current'][$key];
            $previous = $comparison['previous'][$key];
            $change = Format::change($current, $previous);
            $direction = $change === null || $change == 0 ? 'flat' : ($change > 0 ? 'up' : 'down');
            $out[] = [
                'key' => $key,
                'primary' => $primary,
                'value' => self::formatKpi($format, $summary[$key]),
                'current' => self::formatKpi($format, $current),
                'previous' => self::formatKpi($format, $previous),
                'change' => $change,
                'delta' => Format::signedPercent($change),
                'direction' => $direction,
                'tone' => $direction === 'flat' ? 'flat' : ((($direction === 'up') !== $lowerIsBetter) ? 'good' : 'bad'),
                'spark' => LineChart::sparkline(self::daily($series, $key)),
            ];
        }

        return $out;
    }

    /**
     * Daily values of a summary metric, or [] when the daily series does not have it.
     *
     * @param  list<array<string, int|float|string>>  $series
     * @return list<int|float>
     */
    public static function daily(array $series, string $metric): array
    {
        if ($series === [] || ! array_key_exists($metric, $series[0])) {
            return [];
        }

        return array_map(fn (array $row): int|float => is_float($row[$metric]) ? $row[$metric] : (int) $row[$metric], $series);
    }

    private static function formatKpi(string $format, mixed $value): string
    {
        $number = is_numeric($value) ? $value + 0 : 0;

        return match ($format) {
            'percent' => Format::percent($number),
            'duration' => Format::duration((int) $number),
            'decimal' => Format::number($number, 2),
            default => Format::number($number),
        };
    }

    /**
     * How a report card is laid out: the label column, the other text columns shown under the label,
     * the main metric, and whether a details view with every column is needed.
     *
     * @return array{label: string, sub: list<string>, metric: string, details: bool}
     */
    public static function layout(string $report): array
    {
        $columns = StatsQuery::COLUMNS[$report];
        $metric = StatsQuery::mainMetric($report);
        $text = array_values(array_filter($columns, self::isText(...)));
        $numbers = array_filter($columns, fn (string $c): bool => ! self::isText($c) && $c !== $metric);

        return ['label' => $text[0], 'sub' => array_slice($text, 1), 'metric' => $metric, 'details' => $numbers !== []];
    }

    /** Display text of any report cell: percentages, durations, numbers, or the translated text. */
    public static function cell(string $report, string $column, int|float|string|null $value): string
    {
        if ($value === null && ! self::isText($column)) {
            return '–'; // a rate without its base, such as the bounce rate of days without visit data
        }
        if ($column === 'bounce_rate' || self::isRate($column)) {
            return Format::percent((float) $value);
        }
        if ($column === 'avg_seconds') {
            return Format::duration((int) $value);
        }
        if (! self::isText($column)) {
            return Format::number((int) $value);
        }

        return self::text($report, $column, $value === null ? null : (string) $value);
    }

    /**
     * Data of a chart for its hover tooltip: formatted dates, metric labels, values and axis maxima.
     *
     * @param  array{layers: array<string, array{top: int, values: list<int>}>, dates: list<string>}  $chart  LineChart::build()
     * @return array{dates: list<string>, metrics: array<string, array{label: string, values: list<int>, top: int}>}
     */
    public static function tooltip(array $chart): array
    {
        $metrics = [];
        foreach ($chart['layers'] as $metric => $layer) {
            $metrics[$metric] = ['label' => (string) __('stats::dashboard.metrics.'.$metric), 'values' => $layer['values'], 'top' => $layer['top']];
        }

        return ['dates' => array_map(fn (string $d): string => Format::date($d, 'weekday'), $chart['dates']), 'metrics' => $metrics];
    }

    public static function isText(string $column): bool
    {
        return in_array($column, self::TEXT_COLUMNS, true);
    }

    /** Header of a report column. */
    public static function header(string $report, string $column): string
    {
        $key = $column === 'value' ? 'stats::reports.values.'.$report : 'stats::reports.columns.'.$column;

        return (string) __($key);
    }

    /** Display text of a report cell: translated fixed values, '–' for null, the stored text otherwise. */
    public static function text(string $report, string $column, ?string $value): string
    {
        if ($value === null) {
            return '–';
        }
        $enum = $column === 'value' ? $report : $column;
        $key = 'stats::reports.enums.'.$enum.'.'.$value;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $value;
    }

    /** "Mar 4–10", "Feb 25 – Mar 3" or "Mar 9" (formats from the dashboard translations). */
    public static function dates(string $from, string $to): string
    {
        $a = CarbonImmutable::parse($from)->locale(app()->getLocale());
        $b = CarbonImmutable::parse($to)->locale(app()->getLocale());
        $thisYear = $a->year === $b->year && $b->year === CarbonImmutable::now()->year;
        $format = (string) __($thisYear ? 'stats::dashboard.date_formats.day' : 'stats::dashboard.date_formats.day_year');

        if ($a->equalTo($b)) {
            return $a->isoFormat($format);
        }
        if ($thisYear && $a->month === $b->month) {
            return $a->isoFormat($format).'–'.$b->isoFormat((string) __('stats::dashboard.date_formats.day_only'));
        }

        return $a->isoFormat($format).' – '.$b->isoFormat($format);
    }

    /**
     * Slices of a donut chart: the top rows by the report's main metric plus "other" for the rest of the total.
     *
     * @param  list<array<string, int|float|string|null>>  $rows
     * @return list<array{label: string, value: int, other: bool, share: float, offset: float}>
     */
    public static function donut(string $report, array $rows, int $total, int $top = 5): array
    {
        if ($total <= 0) {
            return [];
        }
        $metric = StatsQuery::mainMetric($report);
        $key = StatsQuery::COLUMNS[$report][0];
        $slices = [];
        $sum = 0;
        foreach (array_slice($rows, 0, $top) as $row) {
            $value = (int) $row[$metric];
            $sum += $value;
            $slices[] = ['label' => self::text($report, $key, $row[$key] === null ? null : (string) $row[$key]), 'value' => $value, 'other' => false];
        }
        if ($total > $sum) {
            $slices[] = ['label' => (string) __('stats::dashboard.other'), 'value' => $total - $sum, 'other' => true];
        }

        $offset = 0.0;
        $out = [];
        foreach ($slices as $slice) {
            $share = $slice['value'] / $total;
            $out[] = $slice + ['share' => $share, 'offset' => $offset];
            $offset += $share;
        }

        return $out;
    }
}
