<?php

namespace N2ns\Stats\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\Period;
use N2ns\Stats\Reports\StatsQuery;

/** Per site: the top 5 traffic sources of the last 7 days. */
class TopSources extends Widget
{
    protected string $view = 'stats::filament.top-sources';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('viewStats');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $stats = app(StatsQuery::class);
        $sites = [];
        foreach (Site::query()->orderBy('name')->orderBy('id')->get() as $site) {
            $period = Period::preset('7d', $site->timezone);
            $sites[] = [
                'name' => $site->name,
                'url' => route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']),
                'sources' => $stats->breakdown($site, 'sources', $period->from, $period->to, 5),
            ];
        }

        return ['sites' => $sites];
    }
}
