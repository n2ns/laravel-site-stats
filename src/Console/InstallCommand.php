<?php

namespace N2ns\Stats\Console;

use Illuminate\Console\Command;
use N2ns\Stats\Http\Middleware\TrackAiFetches;

class InstallCommand extends Command
{
    protected $signature = 'stats:install {--force : Run the migrations without asking in production}';

    protected $description = 'Publish the N2N Stats config, run its migrations and show the next steps';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'stats-config']);

        if (config('stats.mode') === 'host') {
            $this->call('migrate', ['--force' => (bool) $this->option('force')]);
            $this->printHostSteps();
        } else {
            $this->components->info('Client mode: no tables are needed.');
            $this->printClientSteps();
        }

        return self::SUCCESS;
    }

    private function printHostSteps(): void
    {
        $this->newLine();
        $this->components->info('N2N Stats is installed. Next steps:');
        $this->line('  1. Allow your admins to see the dashboard, e.g. in AppServiceProvider::boot():');
        $this->line("       Gate::define('viewStats', fn (\$user) => \$user->is_admin);");
        $this->line('  2. Create a site and copy its embed snippet:');
        $this->line('       php artisan stats:site create --name="Example" --domain=example.com');
        $this->line('  3. Add the snippet to the <head> of every page of that site.');
        $this->line('  4. Make sure the Laravel scheduler runs every minute (stats:maintain is scheduled every 5 minutes):');
        $this->line('       * * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1');
        $this->line('  5. Optional: count AI fetchers of this app with the '.TrackAiFetches::class.' middleware.');
        $this->line('  6. Optional: register N2ns\Stats\Filament\StatsPlugin in your Filament panel.');
        $this->newLine();
        $this->line('  Dashboard: '.url((string) config('stats.dashboard_path')));
    }

    private function printClientSteps(): void
    {
        $this->newLine();
        $this->line('  1. On the host, run: php artisan stats:site key --site=<public id>');
        $this->line('  2. Put the printed STATS_CLIENT_* lines in this app\'s .env.');
        $this->line('  3. Add the '.TrackAiFetches::class.' middleware to your web routes.');
    }
}
