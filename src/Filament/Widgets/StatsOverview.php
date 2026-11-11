<?php

namespace N2ns\Stats\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Http\View\Format;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\Period;
use N2ns\Stats\Reports\StatsQuery;

/**
 * Per site: today's and the last 7 days' visitors and pageviews, with the change between the compared complete days
 * (see Period::comparison()).
 */
class StatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return (string) __('stats::filament.overview_heading');
    }

    public static function canView(): bool
    {
        return Gate::allows('viewStats');
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $stats = app(StatsQuery::class);
        $cards = [];
        foreach (Site::query()->orderBy('name')->orderBy('id')->get() as $site) {
            foreach (['today', '7d'] as $range) {
                $summary = $stats->summary($site, ...self::dates(Period::preset($range, $site->timezone)));
                $comparison = $summary['comparison'];
                $url = StatsDashboard::urlFor($site->public_id, $range);
                foreach (['visitors', 'pageviews'] as $metric) {
                    $change = Format::change($comparison['current'][$metric], $comparison['previous'][$metric]);
                    $label = __('stats::filament.card', [
                        'site' => $site->name, 'metric' => __('stats::filament.card_metrics.'.$metric), 'range' => __('stats::filament.card_ranges.'.$range),
                    ]);
                    $cards[] = Stat::make($label, Format::number($summary[$metric]))
                        ->description(__('stats::filament.change', [
                            'change' => Format::signedPercent($change),
                            'current' => Dashboard::dates($comparison['current_from'], $comparison['current_to']),
                            'previous' => Dashboard::dates($comparison['previous_from'], $comparison['previous_to']),
                        ]))
                        ->descriptionColor($change === null || $change == 0 ? 'gray' : ($change > 0 ? 'success' : 'danger'))
                        ->url($url);
                }
            }
        }

        return $cards;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function dates(Period $period): array
    {
        return [$period->from, $period->to];
    }
}
