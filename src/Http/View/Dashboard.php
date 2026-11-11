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
        'pages' => ['pages', 'entries', 'exits', 'page_types', 'content', 'transitions'],
        'sources' => ['source_types', 'sources', 'campaigns', 'landing_pages', 'campaign_pages', 'search_pages'],
        'visitors' => ['countries', 'devices', 'browsers', 'os', 'languages', 'locales'],
        'events' => ['events', 'outbound', 'downloads', 'event_pages'],
        'ai' => ['ai_referrals', 'ai_pages', 'ai_fetchers', 'ai_fetched_pages'],
    ];

    /** Reports that also get a donut chart of their main metric. */
    public const DONUTS = ['source_types', 'countries', 'devices', 'locales', 'page_types'];

    /** Metrics of the daily chart and its data table. */
    public const CHART_METRICS = ['visitors', 'visits', 'pageviews', 'events', 'outbound'];

    /** Report columns whose values are text (everything else is a number). */
    private const TEXT_COLUMNS = [
        'path', 'title', 'page_type', 'content_group', 'from_path', 'to_path', 'source_type', 'source', 'utm_medium',
        'utm_campaign', 'utm_content', 'utm_term', 'value', 'target', 'label', 'name', 'kind', 'fetch_type',
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

        return [
            'site' => $site,
            'range' => $range,
            'period' => $period,
            'rangeError' => $error,
            'summary' => $stats->summary($site, $period->from, $period->to),
            'series' => $stats->timeseries($site, $period->from, $period->to),
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
     * @return list<array{label: string, value: int, share: float, offset: float}>
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
            $slices[] = ['label' => self::text($report, $key, $row[$key] === null ? null : (string) $row[$key]), 'value' => $value];
        }
        if ($total > $sum) {
            $slices[] = ['label' => (string) __('stats::dashboard.other'), 'value' => $total - $sum];
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
