<?php

namespace N2ns\Stats\Collector;

/** Browser payload (design §5.1). */
final readonly class Payload
{
    public const TYPES = ['pageview', 'outbound', 'download', 'event'];

    public function __construct(
        public string $site,
        public string $type,
        public string $url,
        public ?string $referrer = null,
        public ?string $name = null,
        public ?string $label = null,
        public ?int $width = null,
    ) {}

    /** Parses and validates the raw request body; null when malformed (=> HTTP 400). */
    public static function fromJson(string $body): ?self
    {
        if ($body === '' || strlen($body) > 4096) {
            return null;
        }

        $data = json_decode($body, true);
        if (! is_array($data) || array_is_list($data)) {
            return null;
        }

        $site = $data['s'] ?? null;
        $type = $data['t'] ?? null;
        $url = $data['u'] ?? null;
        if (! is_string($site) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $site) !== 1
            || ! is_string($type) || ! in_array($type, self::TYPES, true)
            || ! is_string($url) || ! self::isHttpUrl($url)) {
            return null;
        }

        $referrer = null;
        if ($type === 'pageview' && isset($data['r']) && $data['r'] !== '') {
            if (! is_string($data['r']) || strlen($data['r']) > 2048) {
                return null;
            }
            $referrer = $data['r'];
        }

        $name = null;
        $label = null;
        if ($type === 'event') {
            $name = $data['n'] ?? null;
            if (! is_string($name) || preg_match('/^[a-z0-9_.-]{1,64}$/', $name) !== 1) {
                return null;
            }
            if (isset($data['l']) && $data['l'] !== '') {
                if (! is_string($data['l']) || mb_strlen($data['l']) > 120 || ! mb_check_encoding($data['l'], 'UTF-8')) {
                    return null;
                }
                $label = $data['l'];
            }
        } elseif ($type !== 'pageview') {
            $label = $data['l'] ?? null;
            if (! is_string($label) || ! self::isHttpUrl($label)) {
                return null;
            }
        }

        $width = null;
        if (isset($data['w'])) {
            if (! is_int($data['w']) || $data['w'] < 0 || $data['w'] > 10000) {
                return null;
            }
            $width = $data['w'];
        }

        return new self(strtoupper($site), $type, $url, $referrer, $name, $label, $width);
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
