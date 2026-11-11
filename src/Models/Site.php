<?php

namespace N2ns\Stats\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use N2ns\Stats\Support\Tables;

/**
 * @property int $id
 * @property string $public_id 26-char ULID used in the tracking snippet
 * @property string $name
 * @property list<string> $domains allowed hostnames; '*.example.com' also matches subdomains
 * @property string $timezone daily aggregates are bucketed in this timezone
 * @property string|null $server_key_hash sha256 of the client-mode key
 * @property list<string>|null $locales URL path prefixes of the site's language versions, e.g. ['en', 'zh-cn']
 * @property string|null $default_locale language of pages without a locale prefix
 * @property list<string>|null $exclude_paths path patterns that are never counted; '*' matches any characters
 * @property bool $public_counts whether all-time page and event counts can be read without a token
 */
class Site extends Model
{
    /** Limits and formats of the site settings, shared by the dashboard, the Filament resource and stats:site. */
    public const MAX_DOMAINS = 50;

    public const MAX_LOCALES = 20;

    public const MAX_EXCLUDE_PATHS = 50;

    public const MAX_PATH_LENGTH = 200;

    private const LOCALE = '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/';

    private const HOSTNAME = '/^(\*\.)?(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /** Tables with per-site rows, deleted together with the site. */
    public const STATISTICS_TABLES = [
        'uniques', 'visits', 'daily_totals', 'daily_pages', 'daily_sources', 'daily_dimensions', 'daily_events',
        'daily_entries', 'daily_transitions',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['server_key_hash'];

    protected $casts = ['domains' => 'array', 'locales' => 'array', 'exclude_paths' => 'array', 'public_counts' => 'boolean'];

    /** URLs identify a site by its public id, never by its sequential id (the Filament resource binds records by it). */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getTable(): string
    {
        return Tables::name('sites');
    }

    protected static function booted(): void
    {
        static::creating(function (Site $site): void {
            $site->public_id ??= (string) Str::ulid();
            $site->timezone ??= 'UTC';
        });
    }

    /** Whether $host (lower-case, no port) is one of this site's domains. */
    public function allowsHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        foreach ($this->domains as $domain) {
            $domain = strtolower($domain);
            if (str_starts_with($domain, '*.')) {
                $base = substr($domain, 2);
                if ($host === $base || str_ends_with($host, '.'.$base)) {
                    return true;
                }
            } elseif ($host === $domain || $host === 'www.'.$domain) {
                return true;
            }
        }

        return false;
    }

    public function verifiesServerKey(string $key): bool
    {
        return $this->server_key_hash !== null && hash_equals($this->server_key_hash, hash('sha256', $key));
    }

    /**
     * Language version of a page: its first path segment when that is one of the site's locales, otherwise the
     * default locale. Null when the site has no locales configured.
     */
    public function localeOf(string $path): ?string
    {
        if ($this->locales === null || $this->locales === []) {
            return null;
        }
        $first = strtolower(explode('/', ltrim($path, '/'))[0]);
        foreach ($this->locales as $locale) {
            if ($first === strtolower($locale)) {
                return strtolower($locale);
            }
        }

        return $this->default_locale !== null && $this->default_locale !== '' ? strtolower($this->default_locale) : '(default)';
    }

    /** Whether $path matches one of the site's excluded path patterns ('*' matches any characters). */
    public function excludesPath(string $path): bool
    {
        foreach ($this->exclude_paths ?? [] as $pattern) {
            $regex = '#^'.str_replace('\\*', '.*', preg_quote($pattern, '#')).'$#';
            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The site that the host app's own pages belong to: the one set in `host_site`, otherwise the site whose domains
     * allow $host (only safe when the web server rejects unknown host names; the oldest one when several do).
     * Null when there is none.
     */
    public static function forHost(string $host): ?self
    {
        $pinned = config('stats.host_site');
        if (is_string($pinned) && $pinned !== '') {
            return self::query()->where('public_id', $pinned)->first();
        }

        return self::query()->orderBy('id')->get()->first(fn (self $site): bool => $site->allowsHost($host));
    }

    /** A lower-case hostname with at least two labels, optionally starting with "*."; not an IP address. */
    public static function isValidDomain(string $domain): bool
    {
        return preg_match(self::HOSTNAME, $domain) === 1;
    }

    /** A lower-case URL path prefix of a language version, e.g. "en" or "zh-cn". */
    public static function isValidLocale(string $locale): bool
    {
        return preg_match(self::LOCALE, $locale) === 1;
    }

    /** An excluded path pattern: starts with "/", at most MAX_PATH_LENGTH characters, no control characters. */
    public static function isValidExcludePath(string $path): bool
    {
        return str_starts_with($path, '/') && mb_strlen($path) <= self::MAX_PATH_LENGTH && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }

    /** Replaces the client-mode server key and returns the new key; only its sha256 is stored. */
    public function rotateServerKey(): string
    {
        $key = Str::random(40);
        $this->forceFill(['server_key_hash' => hash('sha256', $key)])->save();

        return $key;
    }

    /** Deletes the site with all of its statistics. */
    public function deleteWithStatistics(): void
    {
        DB::transaction(function (): void {
            foreach (self::STATISTICS_TABLES as $table) {
                DB::table(Tables::name($table))->where('site_id', $this->id)->delete();
            }
            $this->delete();
        });
    }
}
