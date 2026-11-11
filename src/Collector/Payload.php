<?php

namespace N2ns\Stats\Collector;

/** Browser payload (design §5.1). */
final readonly class Payload
{
    public const TYPES = ['pageview', 'outbound', 'download', 'event', 'engagement'];

    /** Longest time on page an engagement report may carry (one day). */
    public const MAX_SECONDS = 86400;

    public function __construct(
        public string $site,
        public string $type,
        public string $url,
        public ?string $referrer = null,
        public ?string $name = null,
        public ?string $label = null,
        public ?int $width = null,
        public ?string $target = null,
        public ?string $title = null,
        public ?string $pageType = null,
        public ?string $group = null,
        /** engagement: seconds the page was visible and the deepest scroll (percent), in total so far ... */
        public int $seconds = 0,
        public int $scroll = 0,
        /** ... and the totals already reported for this page view (0 in its first report). */
        public int $previousSeconds = 0,
        public int $previousScroll = 0,
    ) {}

    /** Parses and validates the raw request body; null when malformed (=> HTTP 400). */
    public static function fromJson(string $body): ?self
    {
        if ($body === '' || strlen($body) > 4096) {
            return null;
        }

        $data = json_decode($body, true);

        return is_array($data) && ! array_is_list($data) ? self::fromArray($data) : null;
    }

    /**
     * Validates decoded payload fields (also used for server-side pageviews and events); null when malformed.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $site = $data['s'] ?? null;
        $type = $data['t'] ?? null;
        $url = $data['u'] ?? null;
        if (! is_string($site) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $site) !== 1
            || ! is_string($type) || ! in_array($type, self::TYPES, true)
            || ! is_string($url) || ! self::isHttpUrl($url)) {
            return null;
        }

        $fields = ['referrer' => null, 'name' => null, 'label' => null, 'target' => null, 'title' => null, 'pageType' => null, 'group' => null];
        $ok = true;
        $text = function (string $key, int $max) use ($data, &$ok): ?string {
            if (! isset($data[$key]) || $data[$key] === '') {
                return null;
            }
            $value = $data[$key];
            if (! is_string($value) || mb_strlen($value) > $max || ! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                $ok = false;

                return null;
            }

            return $value;
        };

        $engagement = [0, 0, 0, 0];
        if ($type === 'engagement') {
            $engagement = self::engagement($data);
            if ($engagement === null) {
                return null;
            }
        } elseif ($type === 'pageview') {
            if (isset($data['r']) && $data['r'] !== '') {
                if (! is_string($data['r']) || strlen($data['r']) > 2048) {
                    return null;
                }
                // A referrer that is not clean UTF-8 (possible in a forwarded Referer header) is ignored, not stored.
                $clean = mb_check_encoding($data['r'], 'UTF-8') && preg_match('/[\x00-\x1F\x7F]/', $data['r']) !== 1;
                $fields['referrer'] = $clean ? $data['r'] : null;
            }
            $fields['title'] = $text('pt', 200);
            $fields['group'] = $text('pg', 120);
            $pageType = $data['py'] ?? null;
            if ($pageType !== null && $pageType !== '') {
                if (! is_string($pageType) || preg_match('/^[a-z0-9_-]{1,40}$/', $pageType) !== 1) {
                    return null;
                }
                $fields['pageType'] = $pageType;
            }
        } else {
            $fields['label'] = $text('l', 120);
            $target = $data['g'] ?? null;
            if ($target !== null && $target !== '') {
                if (! is_string($target) || ! self::isHttpUrl($target)) {
                    return null;
                }
                $fields['target'] = $target;
            } elseif ($type !== 'event') {
                return null; // outbound and download need the link target
            }
            if ($type === 'event') {
                $name = $data['n'] ?? null;
                if (! is_string($name) || preg_match('/^[a-z0-9_.-]{1,64}$/', $name) !== 1) {
                    return null;
                }
                $fields['name'] = $name;
            }
        }
        if (! $ok) {
            return null;
        }

        $width = null;
        if (isset($data['w'])) {
            if (! is_int($data['w']) || $data['w'] < 0 || $data['w'] > 10000) {
                return null;
            }
            $width = $data['w'];
        }

        return new self(
            strtoupper($site), $type, $url, $fields['referrer'], $fields['name'], $fields['label'], $width,
            $fields['target'], $fields['title'], $fields['pageType'], $fields['group'], ...$engagement,
        );
    }

    /**
     * e and sp: total seconds and deepest scroll so far; e0 and sp0: what was already reported (default 0).
     *
     * @param  array<mixed>  $data
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    private static function engagement(array $data): ?array
    {
        $values = [];
        foreach (['e' => self::MAX_SECONDS, 'sp' => 100, 'e0' => self::MAX_SECONDS, 'sp0' => 100] as $key => $max) {
            $value = $data[$key] ?? (str_ends_with($key, '0') ? 0 : null);
            if (! is_int($value) || $value < 0 || $value > $max) {
                return null;
            }
            $values[] = $value;
        }
        [$seconds, $scroll, $previousSeconds, $previousScroll] = $values;

        return $previousSeconds <= $seconds && $previousScroll <= $scroll ? $values : null;
    }

    private static function isHttpUrl(string $url): bool
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        return $parts !== false
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && $parts['host'] !== '';
    }
}
