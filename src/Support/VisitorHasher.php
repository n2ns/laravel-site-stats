<?php

namespace N2ns\Stats\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use N2ns\Stats\Models\Site;

/** sha256(daily salt + site public id + ip + user agent); salts live in the salts table for two days. */
class VisitorHasher
{
    public function hash(Site $site, string $ip, string $userAgent, CarbonInterface $siteLocalDate): string
    {
        return hash('sha256', $this->salt($siteLocalDate->toDateString()).$site->public_id.$ip.$userAgent);
    }

    /** The salt of a date, created on first use; insertOrIgnore keeps concurrent first uses on one salt. */
    private function salt(string $date): string
    {
        $table = DB::table(Tables::name('salts'));
        $salt = $table->clone()->where('date', $date)->value('salt');
        if (is_string($salt)) {
            return $salt;
        }

        $table->clone()->insertOrIgnore(['date' => $date, 'salt' => bin2hex(random_bytes(32))]);

        return (string) $table->clone()->where('date', $date)->value('salt');
    }
}
