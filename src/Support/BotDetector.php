<?php

namespace N2ns\Stats\Support;

class BotDetector
{
    /** True for an empty User-Agent, an AI fetcher or any config('stats.bot_user_agents') substring. */
    public function isBot(?string $userAgent): bool
    {
        $ua = strtolower(trim((string) $userAgent));
        if ($ua === '' || $this->aiFetcher($ua) !== null) {
            return true;
        }

        foreach ((array) config('stats.bot_user_agents', []) as $needle) {
            if ($needle !== '' && str_contains($ua, strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    /** The first config('stats.ai_fetchers') entry contained in the User-Agent, or null. */
    public function aiFetcher(?string $userAgent): ?string
    {
        $ua = strtolower((string) $userAgent);
        if ($ua === '') {
            return null;
        }

        foreach ((array) config('stats.ai_fetchers', []) as $fetcher) {
            $fetcher = strtolower((string) $fetcher);
            if ($fetcher !== '' && str_contains($ua, $fetcher)) {
                return $fetcher;
            }
        }

        return null;
    }
}
