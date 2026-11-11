<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use N2ns\Stats\Http\View\Format;
use N2ns\Stats\Reports\StatsQuery;

/** The key figures of the selected range, each with its change over the compared complete days and a daily sparkline. */
class Kpis extends StatsOverviewWidget
{
    use ReadsSelection;

    // Part of the statistics page only, not of the panel's widget discovery (declared here, not in the trait:
    // PHP before 8.5 rejects a trait property whose default differs from the parent's).
    protected static bool $isDiscovered = false;

    // At the top of the page: rendered with it.
    protected static ?bool $isLazyLoaded = false;

    protected ?string $pollingInterval = null;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $selection = $this->selection();
        if ($selection === null) {
            return [];
        }
        $stats = app(StatsQuery::class);
        [$site, $from, $to] = [$selection->site, $selection->period->from, $selection->period->to];
        $summary = $stats->summary($site, $from, $to);
        $series = $stats->timeseries($site, $from, $to);
        $daily = fn (string $metric): array => array_column($series, $metric);

        $cards = [];
        foreach ([
            'visitors' => $daily('visitors'),
            'visits' => $daily('visits'),
            'pageviews' => $daily('pageviews'),
            'pages' => $daily('pages'),
            'views_per_visit' => $daily('views_per_visit'),
            'bounce_rate' => $daily('bounce_rate'),
            'avg_duration_seconds' => $daily('avg_duration_seconds'),
            'avg_page_seconds' => $daily('avg_page_seconds'),
            'events' => $daily('events'),
            'outbound' => $daily('outbound'),
        ] as $metric => $chart) {
            $cards[] = self::card($metric, $summary, $chart);
        }
        $cards[] = Stat::make(__('stats::filament.kpis.realtime'), Format::number($stats->realtimeVisitors($site)))
            ->description(__('stats::filament.realtime_note'))
            ->icon(Heroicon::OutlinedSignal);

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<int|float>|null  $chart
     */
    private static function card(string $metric, array $summary, ?array $chart): Stat
    {
        $comparison = $summary['comparison'];
        $change = Format::change($comparison['current'][$metric], $comparison['previous'][$metric]);
        $better = $change === null || $change == 0 ? null : ($metric === 'bounce_rate' ? $change < 0 : $change > 0);

        $stat = Stat::make(__('stats::filament.kpis.'.$metric), self::value($metric, $summary[$metric]))
            ->description(__('stats::filament.kpi_change', ['change' => Format::signedPercent($change)]))
            ->color($better === null ? 'gray' : ($better ? 'success' : 'danger'));
        if ($change !== null && $change != 0) {
            $stat->descriptionIcon($change > 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown);
        }

        return $chart === null ? $stat : $stat->chart($chart);
    }

    private static function value(string $metric, int|float $value): string
    {
        return match ($metric) {
            'views_per_visit' => Format::number($value, 2),
            'bounce_rate' => Format::percent($value),
            'avg_duration_seconds', 'avg_page_seconds' => Format::duration((int) $value),
            default => Format::number($value),
        };
    }
}
