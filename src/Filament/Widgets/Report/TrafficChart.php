<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Widgets\ChartWidget;
use N2ns\Stats\Filament\Selection;
use N2ns\Stats\Reports\StatsQuery;

/** Line chart of the daily visitors, visits, pageviews, events and outbound clicks of the selected range. */
class TrafficChart extends ChartWidget
{
    use ReadsSelection;

    // At the top of the page: rendered with it.
    protected static ?bool $isLazyLoaded = false;

    public const METRICS = ['visitors', 'visits', 'pageviews', 'events', 'outbound'];

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return (string) __('stats::filament.chart.traffic');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $selection = $this->selection();
        if ($selection === null) {
            return [];
        }

        return self::lines(
            app(StatsQuery::class)->timeseries($selection->site, $selection->period->from, $selection->period->to),
            array_combine(self::METRICS, array_map(fn (string $metric): string => (string) __('stats::filament.metrics.'.$metric), self::METRICS)),
        );
    }

    /**
     * Chart.js data of one line per metric, coloured with the panel colours.
     *
     * @param  list<array<string, int|float|string>>  $series
     * @param  array<string, string>  $labels  metric => label
     * @return array<string, mixed>
     */
    public static function lines(array $series, array $labels): array
    {
        $colors = Selection::colors();
        $datasets = [];
        foreach (array_keys($labels) as $i => $metric) {
            $datasets[] = [
                'label' => $labels[$metric],
                'data' => array_column($series, $metric),
                'borderColor' => $colors[$i % count($colors)],
                'backgroundColor' => $colors[$i % count($colors)],
            ];
        }

        return ['datasets' => $datasets, 'labels' => array_column($series, 'date')];
    }
}
