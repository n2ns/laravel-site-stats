<?php

namespace N2ns\Stats\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;
use N2ns\Stats\Filament\Selection;
use N2ns\Stats\Filament\StatsPlugin;
use N2ns\Stats\Filament\Widgets\Report\AiChart;
use N2ns\Stats\Filament\Widgets\Report\AiKpis;
use N2ns\Stats\Filament\Widgets\Report\Kpis;
use N2ns\Stats\Filament\Widgets\Report\ReportChart;
use N2ns\Stats\Filament\Widgets\Report\ReportTable;
use N2ns\Stats\Filament\Widgets\Report\TrafficChart;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Reports\Period;
use UnitEnum;

/**
 * The statistics of one site inside a Filament panel. The site and range are the page filters, kept in the query
 * string (?filters[site]=<public id>&filters[range]=7d, or range=custom with filters[from] and filters[to]).
 */
class StatsDashboard extends Page
{
    use HasFiltersForm;

    protected static ?string $slug = 'stats';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    /** The filters live only in the URL, so every link opens exactly what it names. */
    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewStats');
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

    /** URL of the page showing $publicId over $range. */
    public static function urlFor(string $publicId, string $range = Selection::DEFAULT_RANGE): string
    {
        return static::getUrl(['filters' => ['site' => $publicId, 'range' => $range]]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        $ranges = [];
        foreach ([...Period::PRESETS, 'custom'] as $range) {
            $ranges[$range] = (string) __('stats::filament.ranges.'.$range);
        }

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['md' => 2, 'xl' => 4])->schema([
                Select::make('site')
                    ->label(__('stats::filament.site'))
                    ->options(fn (): array => Selection::sites()->pluck('name', 'public_id')->all())
                    ->default(fn (): ?string => Selection::sites()->first()?->public_id)
                    ->selectablePlaceholder(false)
                    ->visible(fn (): bool => Selection::sites()->count() > 1), // a single site needs no switcher
                Select::make('range')
                    ->label(__('stats::filament.range'))
                    ->options($ranges)
                    ->default(Selection::DEFAULT_RANGE)
                    ->selectablePlaceholder(false),
                DatePicker::make('from')
                    ->label(__('stats::filament.from'))
                    ->visible(fn (Get $get): bool => $get('range') === 'custom'),
                DatePicker::make('to')
                    ->label(__('stats::filament.to'))
                    ->visible(fn (Get $get): bool => $get('range') === 'custom'),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(fn (): array => $this->components());
    }

    /** @return array<Component> */
    private function components(): array
    {
        $selection = Selection::fromFilters($this->filters);
        if ($selection === null) {
            return [
                EmptyState::make(__('stats::filament.no_sites'))
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->footer(StatsPlugin::get()->hasSitesResource() && SiteResource::canCreate() ? [
                        Action::make('createSite')->label(__('stats::filament.sites.create'))->url(SiteResource::getUrl('create')),
                    ] : []),
            ];
        }

        $today = Period::todayIn($selection->site->timezone);
        [$current, $previous] = $selection->period->comparison($today);
        $tabs = [];
        foreach (Dashboard::TABS as $tab => $reports) {
            $widgets = $tab === 'ai' ? [$this->widget(AiKpis::class, 'ai-kpis'), $this->widget(AiChart::class, 'ai-chart')] : [];
            foreach (array_intersect(Dashboard::DONUTS, $reports) as $report) {
                $widgets[] = $this->widget(ReportChart::class, 'chart-'.$report, ['report' => $report]);
            }
            foreach ($reports as $report) {
                $widgets[] = $this->widget(ReportTable::class, 'table-'.$report, ['report' => $report]);
            }
            $tabs[] = Tab::make(__('stats::filament.tabs.'.$tab))->id($tab)->schema([Grid::make(['lg' => 2])->schema($widgets)]);
        }

        return [
            EmbeddedSchema::make('filtersForm'),
            Callout::make(__('stats::filament.range_error'))
                ->description($selection->error)
                ->danger()
                ->visible($selection->error !== null),
            Text::make(trim(__('stats::filament.compared', [
                'current' => Dashboard::dates($current->from->toDateString(), $current->to->toDateString()),
                'previous' => Dashboard::dates($previous->from->toDateString(), $previous->to->toDateString()),
            ]).' '.($selection->period->isPartial($today) ? __('stats::filament.partial') : '')))->color('gray'),
            $this->widget(Kpis::class, 'kpis'),
            $this->widget(TrafficChart::class, 'traffic'),
            Tabs::make('reports')->label(__('stats::filament.reports'))->tabs($tabs)->persistTabInQueryString('tab'),
            Text::make(__('stats::filament.footer'))->color('gray'),
        ];
    }

    /** @param array<string, mixed> $properties */
    private function widget(string $class, string $key, array $properties = []): Livewire
    {
        return Livewire::make($class, fn (): array => [...$properties, 'pageFilters' => $this->filters])
            ->key('stats-'.$key);
    }
}
