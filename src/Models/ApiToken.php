<?php

namespace N2ns\Stats\Models;

use Illuminate\Database\Eloquent\Model;
use N2ns\Stats\Support\Tables;

/**
 * @property int $id
 * @property string $name
 * @property string $token_hash sha256 of the plain token
 * @property list<int>|null $site_ids null = all sites
 */
class ApiToken extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['site_ids' => 'array', 'last_used_at' => 'datetime'];

    public function getTable(): string
    {
        return Tables::name('api_tokens');
    }

    public static function findByPlain(string $plain): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $plain))->first();
    }

    public function canAccess(Site $site): bool
    {
        return $this->site_ids === null || in_array($site->id, $this->site_ids, true);
    }
}
