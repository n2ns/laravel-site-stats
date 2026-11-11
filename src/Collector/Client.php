<?php

namespace N2ns\Stats\Collector;

use Illuminate\Http\Request;

/** Who sent a payload: what the collector needs from the browser's request, also when it arrives server-side. */
final readonly class Client
{
    public function __construct(
        public string $ip,
        public string $userAgent,
        public ?string $country = null,
        public ?string $acceptLanguage = null,
        /** Origin (or Referer) host to check against the site's domains; null skips the check. */
        public ?string $origin = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        // Beacons are no-cors requests: pages with Referrer-Policy no-referrer/same-origin (and sandboxed iframes)
        // send "Origin: null" and no Referer. A real origin must match; without one only the page URL is checked.
        $origin = $request->headers->get('Origin');
        $origin = ($origin === null || $origin === '' || $origin === 'null') ? $request->headers->get('Referer') : $origin;

        return new self(
            (string) $request->ip(),
            (string) $request->userAgent(),
            $request->headers->get('CF-IPCountry'),
            $request->headers->get('Accept-Language'),
            is_string($origin) && $origin !== '' && $origin !== 'null' ? $origin : null,
        );
    }
}
