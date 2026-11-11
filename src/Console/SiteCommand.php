<?php

namespace N2ns\Stats\Console;

use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\Tables;

class SiteCommand extends Command
{
    protected $signature = 'stats:site
        {action : create|list|update|delete|key}
        {--name= : Site name}
        {--domain=* : Allowed hostname; *.example.com also allows subdomains (repeatable)}
        {--timezone= : Timezone the days are counted in (default UTC)}
        {--locale=* : URL path prefix of a language version, e.g. en or zh-cn (repeatable)}
        {--default-locale= : Language of pages without a locale prefix}
        {--exclude-path=* : Path that is never counted; * matches any characters (repeatable)}
        {--public-counts : Allow reading all-time page and event counts without a token}
        {--no-public-counts : Disallow reading counts without a token}
        {--site= : Public id of the site (update, delete, key)}
        {--force : Delete without asking}';

    protected $description = 'Manage the sites tracked by N2N Stats';

    /** Tables that hold per-site rows, deleted together with the site. */
    public const MAX_LOCALES = 20;

    public const MAX_EXCLUDE_PATHS = 50;

    private const SITE_TABLES = ['uniques', 'visits', 'daily_totals', 'daily_pages', 'daily_sources', 'daily_dimensions', 'daily_events', 'daily_entries', 'daily_transitions'];

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'create' => $this->create(),
            'list' => $this->list(),
            'update' => $this->update(),
            'delete' => $this->delete(),
            'key' => $this->key(),
            default => $this->fail('Unknown action. Use create, list, update, delete or key.'),
        };
    }

    private function create(): int
    {
        $name = $this->validName($this->option('name'));
        $domains = $this->validDomains($this->domainOptions());
        if ($domains === []) {
            return $this->fail('At least one --domain is required.');
        }
        $timezone = $this->validTimezone($this->option('timezone') ?? 'UTC');

        $site = Site::create(['name' => $name, 'domains' => $domains, 'timezone' => $timezone] + $this->siteOptions());

        $this->components->info("Site created: {$site->public_id}");
        $this->line('Add this snippet to the <head> of every page:');
        $this->newLine();
        $this->line(self::snippet($site));
        $this->newLine();

        return self::SUCCESS;
    }

    private function list(): int
    {
        $sites = Site::query()->orderBy('id')->get();
        if ($sites->isEmpty()) {
            $this->components->info('No sites yet. Create one with: php artisan stats:site create --name=... --domain=...');

            return self::SUCCESS;
        }

        $this->table(
            ['Public id', 'Name', 'Domains', 'Timezone', 'Locales', 'Default locale', 'Excluded paths', 'Public counts', 'Server key', 'Created'],
            $sites->map(fn (Site $site) => [
                $site->public_id,
                $site->name,
                implode(', ', $site->domains),
                $site->timezone,
                implode(', ', $site->locales ?? []),
                (string) $site->default_locale,
                implode(', ', $site->exclude_paths ?? []),
                $site->public_counts ? 'yes' : 'no',
                $site->server_key_hash === null ? 'no' : 'yes',
                (string) $site->getAttribute('created_at'),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function update(): int
    {
        $site = $this->findSite();
        $changes = [];
        if ($this->option('name') !== null) {
            $changes['name'] = $this->validName($this->option('name'));
        }
        if ($this->domainOptions() !== []) {
            $changes['domains'] = $this->validDomains($this->domainOptions());
        }
        if ($this->option('timezone') !== null) {
            $changes['timezone'] = $this->validTimezone($this->option('timezone'));
        }
        $changes += $this->siteOptions();
        if ($changes === []) {
            return $this->fail('Nothing to update. Pass --name, --domain, --timezone, --locale, --default-locale, --exclude-path, --public-counts or --no-public-counts.');
        }

        $site->update($changes);
        $this->components->info("Site {$site->public_id} updated.");

        return self::SUCCESS;
    }

    private function delete(): int
    {
        $site = $this->findSite();
        if (! $this->option('force') && ! $this->confirm("Delete site \"{$site->name}\" ({$site->public_id}) and all of its statistics?")) {
            $this->components->warn('Nothing deleted.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($site): void {
            foreach (self::SITE_TABLES as $table) {
                DB::table(Tables::name($table))->where('site_id', $site->id)->delete();
            }
            $site->delete();
        });

        $this->components->info("Site {$site->public_id} and its statistics deleted.");

        return self::SUCCESS;
    }

    private function key(): int
    {
        $site = $this->findSite();
        $key = Str::random(40);
        $site->update(['server_key_hash' => hash('sha256', $key)]);

        $this->components->info("New server key for {$site->public_id}. It is shown only once; any previous key stops working.");
        $this->line('Put these lines in the .env of the client app:');
        $this->newLine();
        $this->line('STATS_MODE=client');
        $this->line('STATS_CLIENT_ENDPOINT='.self::collectorBaseUrl());
        $this->line('STATS_CLIENT_SITE='.$site->public_id);
        $this->line('STATS_CLIENT_KEY='.$key);
        $this->newLine();

        return self::SUCCESS;
    }

    public static function snippet(Site $site): string
    {
        return '<script defer data-site="'.$site->public_id.'" src="'.self::collectorBaseUrl().'/js/stats.js"></script>';
    }

    /**
     * Base URL of the collector routes, e.g. https://stats.example.com. The CLI has no request, so a collector domain
     * is always given as https; without one the collector shares the host app's domain and the URL comes from APP_URL.
     */
    private static function collectorBaseUrl(): string
    {
        $url = Str::beforeLast(route('stats.script'), '/js/stats.js');

        return config('stats.collector_domain') ? 'https://'.Str::after($url, '://') : $url;
    }

    private function findSite(): Site
    {
        $publicId = $this->option('site');
        if (! is_string($publicId) || $publicId === '') {
            $this->fail('--site=<public id> is required.');
        }

        return Site::query()->where('public_id', $publicId)->first()
            ?? $this->fail("No site with public id {$publicId}.");
    }

    private function validName(mixed $name): string
    {
        $name = is_string($name) ? trim($name) : '';
        if ($name === '' || mb_strlen($name) > 120) {
            $this->fail('--name is required (at most 120 characters).');
        }

        return $name;
    }

    /** @return list<string> */
    private function domainOptions(): array
    {
        return array_values(array_filter((array) $this->option('domain'), fn ($d) => is_string($d) && $d !== ''));
    }

    /**
     * @param  list<string>  $domains
     * @return list<string>
     */
    private function validDomains(array $domains): array
    {
        $valid = [];
        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));
            if (! self::isValidDomain($domain)) {
                $this->fail("Invalid domain \"{$domain}\". Use a hostname such as example.com or *.example.com.");
            }
            $valid[] = $domain;
        }

        return array_values(array_unique($valid));
    }

    public static function isValidDomain(string $domain): bool
    {
        $host = str_starts_with($domain, '*.') ? substr($domain, 2) : $domain;
        if (strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) === 1;
    }

    /**
     * Locales, default locale, excluded paths and public counts given as options (only those passed).
     *
     * @return array<string, mixed>
     */
    private function siteOptions(): array
    {
        $options = [];

        $locales = $this->listOption('locale');
        if ($locales !== []) {
            if (count($locales) > self::MAX_LOCALES) {
                $this->fail('At most '.self::MAX_LOCALES.' --locale values are allowed.');
            }
            $options['locales'] = array_values(array_unique(array_map(fn (string $l): string => $this->validLocale($l, '--locale'), $locales)));
        }

        $default = $this->option('default-locale');
        if (is_string($default) && $default !== '') {
            $options['default_locale'] = $this->validLocale($default, '--default-locale');
        }

        $paths = $this->listOption('exclude-path');
        if ($paths !== []) {
            if (count($paths) > self::MAX_EXCLUDE_PATHS) {
                $this->fail('At most '.self::MAX_EXCLUDE_PATHS.' --exclude-path values are allowed.');
            }
            foreach ($paths as $path) {
                if (! str_starts_with($path, '/') || mb_strlen($path) > 200 || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
                    $this->fail("Invalid --exclude-path \"{$path}\". It must start with / and have at most 200 characters.");
                }
            }
            $options['exclude_paths'] = array_values(array_unique($paths));
        }

        if ($this->option('public-counts') && $this->option('no-public-counts')) {
            $this->fail('Pass either --public-counts or --no-public-counts, not both.');
        }
        if ($this->option('public-counts')) {
            $options['public_counts'] = true;
        } elseif ($this->option('no-public-counts')) {
            $options['public_counts'] = false;
        }

        return $options;
    }

    /** @return list<string> */
    private function listOption(string $name): array
    {
        return array_values(array_filter((array) $this->option($name), fn ($v) => is_string($v) && $v !== ''));
    }

    private function validLocale(string $locale, string $option): string
    {
        $locale = strtolower(trim($locale));
        if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $locale) !== 1) {
            $this->fail("Invalid {$option} \"{$locale}\". Use a language code such as en, pt-br or zh-cn.");
        }

        return $locale;
    }

    private function validTimezone(mixed $timezone): string
    {
        if (! is_string($timezone) || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $this->fail('Invalid timezone "'.(is_string($timezone) ? $timezone : '').'". Use an identifier such as UTC or Europe/Berlin.');
        }

        return $timezone;
    }
}
