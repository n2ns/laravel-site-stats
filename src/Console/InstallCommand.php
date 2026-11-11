<?php

namespace N2ns\Stats\Console;

use Illuminate\Console\Command;
use N2ns\Stats\Http\Middleware\TrackAiFetches;
use N2ns\Stats\Http\Middleware\TrackPageviews;
use N2ns\Stats\Models\Site;

class InstallCommand extends Command
{
    protected $signature = 'stats:install
        {--force : Run the migrations without asking in production}
        {--site= : Name of a site to create for this app\'s own pages (single site)}
        {--domain=* : Its domains, with --site (repeatable)}';

    protected $description = 'Publish the N2N Stats config, run its migrations and show the next steps';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'stats-config']);

        if (config('stats.mode') === 'host') {
            if ($this->call('migrate', ['--force' => (bool) $this->option('force')]) !== self::SUCCESS) {
                $this->components->error('The migrations did not run; nothing else was done.');

                return self::FAILURE;
            }
            $site = $this->createSite();
            if ($site === false) {
                return self::FAILURE;
            }
            $this->printHostSteps($site);
        } else {
            if ($this->option('site') !== null || $this->option('domain') !== []) {
                $this->components->error('--site and --domain create a site on the host; this app is in client mode.');

                return self::FAILURE;
            }
            $this->components->info('Client mode: no tables are needed.');
            $this->printClientSteps();
        }

        return self::SUCCESS;
    }

    /**
     * The site given with --site and --domain: created, or the existing site when one already counts the first domain
     * and no other site counts the rest (so installing again changes nothing). Null without --site, false on errors.
     */
    private function createSite(): Site|false|null
    {
        $name = trim((string) $this->option('site'));
        $domains = array_values(array_unique(array_map(fn ($d): string => strtolower(trim((string) $d)), (array) $this->option('domain'))));
        if ($name === '') {
            if ($domains !== []) {
                $this->components->error('--domain needs --site with the name of the site.');

                return false;
            }

            return null;
        }
        if ($domains === [] || mb_strlen($name) > 120 || count($domains) > Site::MAX_DOMAINS) {
            $this->components->error('--site needs a name of at most 120 characters and 1 to '.Site::MAX_DOMAINS.' --domain values.');

            return false;
        }
        foreach ($domains as $domain) {
            if (! Site::isValidDomain($domain)) {
                $this->components->error("Invalid domain \"{$domain}\". Use a hostname such as example.com or *.example.com.");

                return false;
            }
        }

        $sites = Site::query()->orderBy('id')->get();
        $owner = fn (string $domain): ?Site => $sites->first(fn (Site $site): bool => $site->allowsHost(ltrim($domain, '*.')));
        $existing = $owner($domains[0]);
        foreach ($domains as $domain) {
            $other = $owner($domain);
            if ($other !== null && $other->id !== $existing?->id) {
                $this->components->error("Site {$other->name} ({$other->public_id}) already counts {$domain}.");

                return false;
            }
        }
        if ($existing !== null) {
            $this->components->info("Site {$existing->name} ({$existing->public_id}) already counts {$domains[0]}.");

            return $existing;
        }

        $site = Site::create(['name' => $name, 'domains' => $domains, 'timezone' => 'UTC']);
        $this->components->info("Site created: {$site->name} ({$site->public_id}), timezone UTC (change it with stats:site update --timezone).");

        return $site;
    }

    private function printHostSteps(?Site $site): void
    {
        $this->newLine();
        $this->components->info('N2N Stats is installed. Next steps:');
        $this->line('  1. Allow your admins to see the statistics, e.g. in AppServiceProvider::boot():');
        $this->line("       Gate::define('viewStats', fn (\$user) => \$user->is_admin);");
        $this->line('  2. Make sure the Laravel scheduler runs every minute (stats:maintain is scheduled every 5 minutes):');
        $this->line('       * * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1');
        $this->line('  3. Count your sites, in either or both ways:');
        $this->line('     Single site, this app\'s own pages'.($site === null ? ' (create it with --site="Name" --domain=example.com, or stats:site create):' : ':'));
        $this->line('       - put @statsScript in the <head> of your layout;');
        $this->line('       - optional: '.TrackAiFetches::class.' for AI fetchers, and '.TrackPageviews::class);
        $this->line("         for page views on the server (then @statsScript(['data-pageviews' => 'off']) for the events).");
        $this->line('     Multiple sites, other websites: create each one and add its snippet to its pages:');
        $this->line('       php artisan stats:site create --name="Example" --domain=example.com');
        $this->line('  4. Optional: register N2ns\Stats\Filament\StatsPlugin in your Filament panel (then STATS_DASHBOARD_ENABLED=false).');
        $this->newLine();
        if (config('stats.dashboard_enabled', true)) {
            $this->line('  Dashboard: '.url((string) config('stats.dashboard_path')));
        }
    }

    private function printClientSteps(): void
    {
        $this->newLine();
        $this->line('  1. On the host, run: php artisan stats:site key --site=<public id>');
        $this->line('  2. Put the printed STATS_CLIENT_* lines in this app\'s .env.');
        $this->line('  3. Add the '.TrackAiFetches::class.' middleware to your web routes, and optionally');
        $this->line('     '.TrackPageviews::class.' to count page views on the server (then not with the script).');
    }
}
