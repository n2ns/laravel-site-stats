<?php

namespace N2ns\Stats\Tests\Feature;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Closure;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Livewire\LivewireServiceProvider;
use N2ns\Stats\Filament\StatsPlugin;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

/** Registers Filament and a test panel "admin" at /admin with the stats plugin, configured by $configure. */
final class FilamentPanel
{
    /** @param (Closure(StatsPlugin): StatsPlugin)|null $configure */
    public static function register(Application $app, ?Closure $configure = null): void
    {
        $plugin = StatsPlugin::make();
        if ($configure !== null) {
            $plugin = $configure($plugin);
        }
        $app->register(new class($app, $plugin) extends PanelProvider
        {
            public function __construct(Application $app, private StatsPlugin $plugin)
            {
                parent::__construct($app);
            }

            public function panel(Panel $panel): Panel
            {
                return $panel->id('admin')->path('admin')->default()->plugin($this->plugin);
            }
        });
        foreach ([
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
        ] as $provider) {
            $app->register($provider);
        }
        Filament::setCurrentPanel('admin');
    }

    public static function allowStats(): void
    {
        Gate::define('viewStats', fn ($user = null) => true);
    }
}
