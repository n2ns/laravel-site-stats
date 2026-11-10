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
 */
class Site extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['server_key_hash'];

    protected $casts = ['domains' => 'array'];

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
}
