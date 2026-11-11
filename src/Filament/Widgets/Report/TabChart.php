<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Widgets\Widget;
use Livewire\Attributes\Locked;
use N2ns\Stats\Http\View\Charts;
use N2ns\Stats\Reports\StatsQuery;

/** One chart of a report tab (Http\View\Charts), drawn with the same inline SVG and HTML as the standalone dashboard. */
class TabChart extends Widget
{
    use ReadsSelection;

    // Part of the statistics page only, not of the panel's widget discovery (declared here, not in the trait:
    // PHP before 8.5 rejects a trait property whose default differs from the parent's).
    protected static bool $isDiscovered = false;

    // Loaded when its tab is shown, so hidden tabs cost nothing on page load.
    protected static ?bool $isLazyLoaded = true;

    protected string $view = 'stats-filament::tab-chart';

    #[Locked]
    public string $chart = 'time_on_page';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $selection = $this->selection();
        $known = in_array($this->chart, array_merge(...array_values(Charts::TABS)), true);

        // Not 'chart': Livewire passes the public property of that name to the view and it would win.
        return ['tabChart' => $selection === null || ! $known ? null : Charts::build(
            app(StatsQuery::class), $selection->site, $selection->period->from, $selection->period->to, $this->chart,
        )];
    }
}
