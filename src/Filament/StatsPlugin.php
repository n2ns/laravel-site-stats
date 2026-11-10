<?php

namespace N2ns\Stats\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use N2ns\Stats\Filament\Widgets\StatsOverview;
use N2ns\Stats\Filament\Widgets\TopSources;

/**
 * Filament panel plugin: $panel->plugin(StatsPlugin::make()). Only loaded when the host registers it,
 * so the package works without Filament installed.
 */
class StatsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'n2ns-stats';
    }

    public function register(Panel $panel): void
    {
        $panel->widgets([StatsOverview::class, TopSources::class]);
    }

    public function boot(Panel $panel): void {}
}
