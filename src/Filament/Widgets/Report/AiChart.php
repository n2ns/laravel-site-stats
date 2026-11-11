<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Widgets\ChartWidget;
use N2ns\Stats\Reports\StatsQuery;

/** Line chart of the daily AI visits, AI visitors and AI fetches of the selected range. */
class AiChart extends ChartWidget
{
    use ReadsSelection;

    // Part of the statistics page only, not of the panel's widget discovery (declared here, not in the trait:
    // PHP before 8.5 rejects a trait property whose default differs from the parent's).
    protected static bool $isDiscovered = false;

    // Loaded when its tab is shown, so hidden tabs cost nothing on page load.
    protected static ?bool $isLazyLoaded = true;

    // Statistics change by the minute; Filament's charts would otherwise ask the server again every 5 s.
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return (string) __('stats::filament.chart.ai');
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
        $metrics = ['visits', 'visitors', 'fetches'];

        return TrafficChart::lines(
            app(StatsQuery::class)->ai($selection->site, $selection->period->from, $selection->period->to)['series'],
            array_combine($metrics, array_map(fn (string $metric): string => (string) __('stats::filament.ai.'.$metric), $metrics)),
        );
    }
}
