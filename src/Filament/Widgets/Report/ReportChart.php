<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Locked;
use N2ns\Stats\Filament\Selection;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Reports\StatsQuery;

/** Doughnut chart of one report's main metric: the top 5 rows and "other". */
class ReportChart extends ChartWidget
{
    use ReadsSelection;

    // Part of the statistics page only, not of the panel's widget discovery (declared here, not in the trait:
    // PHP before 8.5 rejects a trait property whose default differs from the parent's).
    protected static bool $isDiscovered = false;

    // Loaded when its tab is shown, so hidden tabs cost nothing on page load.
    protected static ?bool $isLazyLoaded = true;

    #[Locked]
    public string $report = 'source_types';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return (string) __('stats::reports.titles.'.$this->report);
    }

    public function getDescription(): string
    {
        return (string) __('stats::filament.donut', ['metric' => __('stats::reports.columns.'.StatsQuery::mainMetric($this->report))]);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $selection = $this->selection();
        if ($selection === null || ! in_array($this->report, StatsQuery::REPORTS, true)) {
            return [];
        }
        [$site, $from, $to] = [$selection->site, $selection->period->from, $selection->period->to];
        $stats = app(StatsQuery::class);
        $total = $stats->total($site, $this->report, $from, $to);
        $slices = Dashboard::donut($this->report, $total > 0 ? $stats->breakdown($site, $this->report, $from, $to, 5) : [], $total);
        if ($slices === []) {
            return [];
        }
        $colors = Selection::colors();

        return [
            'datasets' => [[
                'label' => (string) __('stats::reports.columns.'.StatsQuery::mainMetric($this->report)),
                'data' => array_column($slices, 'value'),
                'backgroundColor' => array_map(fn (int $i): string => $colors[$i % count($colors)], array_keys($slices)),
            ]],
            'labels' => array_column($slices, 'label'),
        ];
    }
}
