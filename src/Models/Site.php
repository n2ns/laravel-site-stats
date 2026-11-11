<?php

namespace N2ns\Stats\Models;

use Illuminate\Database\Eloquent\Model;
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
    protected $guarded = ['id'];

    protected $hidden = ['server_key_hash'];

    protected $casts = ['domains' => 'array', 'locales' => 'array', 'exclude_paths' => 'array', 'public_counts' => 'boolean'];

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
}
