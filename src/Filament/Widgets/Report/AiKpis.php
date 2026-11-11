<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use N2ns\Stats\Http\View\Format;
use N2ns\Stats\Reports\StatsQuery;

/** AI visits, AI visitors and AI fetches of the selected range, with daily sparklines. */
class AiKpis extends StatsOverviewWidget
{
    use ReadsSelection;

    // Loaded when its tab is shown, so hidden tabs cost nothing on page load.
    protected static ?bool $isLazyLoaded = true;

    protected ?string $pollingInterval = null;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $selection = $this->selection();
        if ($selection === null) {
            return [];
        }
        $ai = app(StatsQuery::class)->ai($selection->site, $selection->period->from, $selection->period->to);

        $cards = [];
        foreach (['visits', 'visitors', 'fetches'] as $metric) {
            $cards[] = Stat::make(__('stats::filament.ai.'.$metric), Format::number($ai[$metric]))
                ->description(__('stats::filament.ai.'.$metric.'_note'))
                ->chart(array_map(fn (int $value): float => $value, array_column($ai['series'], $metric)))
                ->color('primary');
        }

        return $cards;
    }
}
