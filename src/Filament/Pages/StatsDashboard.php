<?php

namespace N2ns\Stats\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Filament\StatsPlugin;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\StatsQuery;
use UnitEnum;

/** The full statistics dashboard inside a Filament panel; ?site=, ?range=, ?from= and ?to= select what is shown. */
class StatsDashboard extends Page
{
    protected static ?string $slug = 'stats';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'stats::filament.dashboard';

    /** @var array{site: string, range: string, from: string, to: string} the query parameters (not named like the view variables) */
    public array $selection = ['site' => '', 'range' => '', 'from' => '', 'to' => ''];

    public static function canAccess(): bool
    {
        return Gate::allows('viewStats');
    }

    public function mount(): void
    {
        foreach (['site', 'range', 'from', 'to'] as $key) {
            $value = request()->query($key);
            $this->selection[$key] = is_string($value) ? $value : '';
        }
    }

    public static function getNavigationLabel(): string
    {
        return StatsPlugin::get()->getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return StatsPlugin::get()->getNavigationGroup();
    }

    public function getTitle(): string|Htmlable
    {
        return static::getNavigationLabel();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $sites = Site::query()->orderBy('name')->orderBy('id')->get();
        $picked = $this->selection['site'];
        $site = ($picked !== '' ? $sites->firstWhere('public_id', $picked) : null) ?? $sites->first();
        if ($site === null) {
            return ['site' => null];
        }

        $url = static::getUrl();

        return Dashboard::data(app(StatsQuery::class), $site, $this->selection['range'], $this->selection['from'], $this->selection['to']) + [
            'sites' => $sites,
            'siteAction' => $url,
            'rangeAction' => $url,
            'rangeHidden' => ['site' => $site->public_id],
        ];
    }
}
