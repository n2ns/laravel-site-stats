<?php

namespace N2ns\Stats\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Http\View\Format;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\Period;
use N2ns\Stats\Reports\StatsQuery;

/** Per site: today's and the last 7 days' visitors and pageviews, with the change against the previous period. */
class StatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Site statistics';

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
            foreach (['today' => ['today', 'yesterday'], '7d' => ['7 days', 'previous 7 days']] as $range => [$label, $previousLabel]) {
                $summary = $stats->summary($site, ...self::dates(Period::preset($range, $site->timezone)));
                $url = route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => $range]);
                foreach (['visitors' => 'visitors', 'pageviews' => 'pageviews'] as $metric => $metricLabel) {
                    $change = Format::change($summary[$metric], $summary['previous'][$metric]);
                    $cards[] = Stat::make("{$site->name} · {$metricLabel} · {$label}", Format::number($summary[$metric]))
                        ->description(Format::signedPercent($change).' vs '.$previousLabel)
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
