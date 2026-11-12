<?php

namespace N2ns\Stats\Support;

use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use Throwable;

/**
 * The visitor's country from the local country database (`country_database`, a MaxMind DB file such as DB-IP IP to
 * Country Lite or MaxMind GeoLite2 Country). The lookup runs on this server; the IP address is not sent anywhere.
 */
class CountryLookup
{
    private ?Reader $reader = null;

    /** The country's two-letter code as the database gives it; null without a database, or when it has no country. */
    public function country(string $ip): ?string
    {
        $path = config('stats.country_database');
        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $this->reader ??= new Reader($path);
            $record = $this->reader->get($ip);
        } catch (Throwable $e) {
            // A missing or unreadable file (e.g. while it is being replaced) only loses the country, never the event.
            Log::warning('N2N Stats: country database lookup failed', ['exception' => $e::class]);

            return null;
        }

        $code = is_array($record) && is_array($record['country'] ?? null) ? ($record['country']['iso_code'] ?? null) : null;

        return is_string($code) ? $code : null;
    }
}
