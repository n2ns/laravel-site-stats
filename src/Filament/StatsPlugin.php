<?php

namespace N2ns\Stats\Filament;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;
use N2ns\Stats\Filament\Widgets\Report\AiChart;
use N2ns\Stats\Filament\Widgets\Report\AiKpis;
use N2ns\Stats\Filament\Widgets\Report\Kpis;
use N2ns\Stats\Filament\Widgets\Report\ReportChart;
use N2ns\Stats\Filament\Widgets\Report\ReportTable;
use N2ns\Stats\Filament\Widgets\Report\TrafficChart;
use N2ns\Stats\Filament\Widgets\StatsOverview;
use N2ns\Stats\Filament\Widgets\TopSources;

/**
 * Filament panel plugin: $panel->plugin(StatsPlugin::make()). Only loaded when the host registers it,
 * so the package works without Filament installed. Registers the two dashboard widgets, the statistics page and the
 * Sites resource; ->navigationGroup('Reports')->navigationLabel('Analytics') place them in the panel navigation, and
 * ->sitesResource(false) leaves the Sites resource out.
 */
class StatsPlugin implements Plugin
{
    public const ID = 'n2ns-stats';

    private ?string $navigationGroup = null;

    private ?string $navigationLabel = null;

    private bool $sitesResource = true;

    public static function make(): static
    {
        return app(static::class);
    }

    /** The plugin registered on the current panel. */
    public static function get(): static
    {
        /** @var static */
        return Filament::getCurrentOrDefaultPanel()->getPlugin(self::ID);
    }

    public function getId(): string
    {
        return self::ID;
    }

    /** Navigation group of the statistics page; none by default. */
    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    /** Navigation label (and title) of the statistics page; "Statistics" (translated) by default. */
    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return $this->navigationLabel ?? (string) __('stats::filament.navigation_label');
    }

    /** Whether the Sites resource is registered; true by default. */
    public function sitesResource(bool $condition = true): static
    {
        $this->sitesResource = $condition;

        return $this;
    }

    public function hasSitesResource(): bool
    {
        return $this->sitesResource;
    }

    public function register(Panel $panel): void
    {
        $panel->widgets([StatsOverview::class, TopSources::class])
            ->pages([StatsDashboard::class])
            ->resources($this->sitesResource ? [SiteResource::class] : [])
            // The statistics page's own widgets, mounted by the page rather than shown on the panel dashboard.
            ->livewireComponents([Kpis::class, TrafficChart::class, AiKpis::class, AiChart::class, ReportChart::class, ReportTable::class]);
    }

    public function boot(Panel $panel): void {}
}
