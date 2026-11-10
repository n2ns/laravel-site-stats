<?php

namespace N2ns\Stats\Support;

/**
 * Classifies the source of a visit (design §5.4): utm > (no referrer: click id or direct)
 * > (referrer: own site => direct, ai, search, social, click id, referral). AI comes before search so that
 * gemini.google.com is not matched by 'google.'. Lists come from config.
 */
class SourceClassifier
{
    /**
     * @param  string  $pageUrl  full URL of the entry page (query string carries utm_* and click ids)
     * @param  string|null  $referrer  document.referrer
     * @param  callable(string): bool  $isOwnHost  true for hosts of the same site
     */
    public function classify(string $pageUrl, ?string $referrer, callable $isOwnHost): Source
    {
        parse_str((string) parse_url($pageUrl, PHP_URL_QUERY), $query);

        $utmSource = $this->clean($query['utm_source'] ?? null);
        if ($utmSource !== null) {
            return new Source(
                'utm',
                $utmSource,
                $this->clean($query['utm_medium'] ?? null),
                $this->clean($query['utm_campaign'] ?? null),
                $this->clean($query['utm_content'] ?? null),
                $this->clean($query['utm_term'] ?? null),
            );
        }

        $host = $this->referrerHost($referrer);
        if ($host === null) {
            return $this->clickId($query) ?? Source::direct();
        }

        if ($isOwnHost($host)) {
            return Source::direct();
        }

        $source = substr(str_starts_with($host, 'www.') ? substr($host, 4) : $host, 0, 191);

        // Exact AI hosts are checked before the search list so gemini.google.com is not counted as google search.
        if (in_array($host, (array) config('stats.ai_hosts', []), true) || in_array($source, (array) config('stats.ai_hosts', []), true)) {
            return new Source('ai', $source);
        }
        if ($this->matchesAny($host, (array) config('stats.search_hosts', []))) {
            return new Source('search', $source);
        }
        if ($this->matchesAny($host, (array) config('stats.social_hosts', []))) {
            return new Source('social', $source);
        }

        return $this->clickId($query) ?? new Source('referral', $source);
    }

    private function referrerHost(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }

        $parts = parse_url($referrer);
        if ($parts === false || ! isset($parts['host'], $parts['scheme']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        return $host === '' ? null : $host;
    }

    /**
     * Matches list entries on label boundaries: 'google.' matches google.com and www.google.co.uk,
     * 't.co' matches t.co and www.t.co but not microsoft.com.
     *
     * @param  array<mixed>  $entries
     */
    private function matchesAny(string $host, array $entries): bool
    {
        foreach ($entries as $entry) {
            $entry = strtolower((string) $entry);
            if ($entry === '') {
                continue;
            }
            if (str_ends_with($entry, '.')) {
                if (str_starts_with($host, $entry) || str_contains($host, '.'.$entry)) {
                    return true;
                }
            } elseif ($host === $entry || str_ends_with($host, '.'.$entry)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<mixed>  $query */
    private function clickId(array $query): ?Source
    {
        foreach ((array) config('stats.click_ids', []) as $param => $target) {
            if (is_string($query[$param] ?? null) && $query[$param] !== '' && is_array($target) && count($target) === 2) {
                return new Source((string) $target[0], (string) $target[1]);
            }
        }

        return null;
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
        if ($value === '' || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        return mb_substr($value, 0, 128);
    }
}
