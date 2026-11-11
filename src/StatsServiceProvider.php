<?php

namespace N2ns\Stats;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use N2ns\Stats\Console\InstallCommand;
use N2ns\Stats\Console\MaintainCommand;
use N2ns\Stats\Console\SiteCommand;
use N2ns\Stats\Console\TokenCommand;

class StatsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/stats.php', 'stats');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/stats.php' => config_path('stats.php')], 'stats-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, SiteCommand::class, TokenCommand::class, MaintainCommand::class]);
        }

        // @statsScript or @statsScript(['data-pageviews' => 'off']) in the layout of the app's own pages.
        Blade::directive('statsScript', fn (string $expression): string => '<?php echo \\N2ns\\Stats\\Stats::script('.$expression.'); ?>');

        if (config('stats.mode') !== 'host') {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'stats');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'stats');

        // Hosts grant access with Gate::define('viewStats', ...); denied by default.
        if (! Gate::has('viewStats')) {
            Gate::define('viewStats', fn ($user = null) => false);
        }

        RateLimiter::for('stats-api', fn (Request $request) => Limit::perMinute((int) config('stats.rate_limits.api_per_minute'))
            ->by(hash('sha256', (string) $request->bearerToken())));

        $this->registerRoutes();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('stats:maintain')->everyFiveMinutes()->withoutOverlapping();
        });
    }

    private function registerRoutes(): void
    {
        $collector = Route::middleware([]);
        if ($domain = config('stats.collector_domain')) {
            $collector->domain($domain);
        }
        $collector->prefix((string) config('stats.collector_path'))->group(__DIR__.'/../routes/collector.php');

        if (config('stats.dashboard_enabled', true)) {
            Route::middleware(config('stats.dashboard_middleware', ['web']))
                ->prefix((string) config('stats.dashboard_path'))
                ->group(__DIR__.'/../routes/dashboard.php');
        }
    }
}
