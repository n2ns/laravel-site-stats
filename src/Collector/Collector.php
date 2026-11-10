<?php

namespace N2ns\Stats\Collector;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Recording\Recorder;
use N2ns\Stats\Support\BotDetector;
use N2ns\Stats\Support\PathNormalizer;
use N2ns\Stats\Support\SourceClassifier;
use N2ns\Stats\Support\UserAgentParser;
use N2ns\Stats\Support\VisitorHasher;
use Throwable;

/** Runs design §5.2 steps 2-6 for a parsed payload. Never throws; rejected payloads are dropped silently. */
class Collector
{
    public function __construct(
        private readonly Recorder $recorder,
        private readonly BotDetector $bots,
        private readonly VisitorHasher $hasher,
        private readonly PathNormalizer $paths,
        private readonly SourceClassifier $sources,
        private readonly UserAgentParser $userAgents,
    ) {}

    public function collect(Payload $payload, Request $request): void
    {
        try {
            $this->process($payload, $request);
        } catch (Throwable $e) {
            // Only the exception class: messages of query exceptions contain bound values (visitor hashes, paths).
            Log::warning('N2N Stats: failed to record an event', ['type' => $payload->type, 'exception' => $e::class]);
        }
    }

    private function process(Payload $payload, Request $request): void
    {
        $site = Site::query()->where('public_id', $payload->site)->first();
        if ($site === null) {
            return;
        }

        $pageHost = $this->publicHost($payload->url);
        if ($pageHost === null || ! $site->allowsHost($pageHost)) {
            return;
        }

        // Beacons are no-cors requests: pages with Referrer-Policy no-referrer/same-origin (and sandboxed iframes)
        // send "Origin: null" and no Referer. A real origin must match; without one only the page URL is checked.
        $origin = $request->headers->get('Origin');
        $origin = ($origin === null || $origin === '' || $origin === 'null') ? $request->headers->get('Referer') : $origin;
        if (is_string($origin) && $origin !== '' && $origin !== 'null') {
            $originHost = $this->publicHost($origin);
            if ($originHost === null || ! $site->allowsHost($originHost)) {
                return;
            }
        }

        $ip = (string) $request->ip();
        $limits = (array) config('stats.rate_limits', []);
        if (! $this->allow('stats-collect-site:'.$site->id, (int) ($limits['per_site_per_minute'] ?? 3000))) {
            return;
        }
        // Keyed with the app key and the date, so cache keys cannot be reversed to an IP or linked across days.
        // IPv6 addresses are limited per /64, the block a single host usually controls.
        $ipKey = hash_hmac('sha256', CarbonImmutable::now()->toDateString().'|'.$this->ipBlock($ip), (string) config('app.key'));
        if (! $this->allow('stats-collect-ip:'.$ipKey, (int) ($limits['per_ip_per_minute'] ?? 120))) {
            return;
        }

        $userAgent = (string) $request->userAgent();
        if ($this->bots->isBot($userAgent)) {
            return;
        }

        $now = CarbonImmutable::now();
        $visitorHash = $this->hasher->hash($site, $ip, $userAgent, $now->setTimezone($site->timezone ?: 'UTC'));
        if (! $this->allow('stats-collect-visitor:'.$visitorHash, (int) ($limits['per_visitor_per_minute'] ?? 60))) {
            return;
        }

        $path = $this->paths->normalize($payload->url);
        if ($path === null) {
            return;
        }

        if ($payload->type === 'pageview') {
            $source = $this->sources->classify($payload->url, $payload->referrer, fn (string $host): bool => $site->allowsHost($host));
            $this->recorder->recordPageview($site, $visitorHash, $now, $path, $payload->url, $source, $this->dimensions($request, $userAgent, $payload->width));

            return;
        }

        if ($payload->type === 'event') {
            $this->recorder->recordEvent($site, $visitorHash, $now, 'custom', (string) $payload->name, $payload->label, $path);

            return;
        }

        $target = $this->paths->cleanUrl((string) $payload->label);
        if ($target !== null) {
            $this->recorder->recordEvent($site, $visitorHash, $now, $payload->type === 'download' ? 'download' : 'outbound', '', $target, $path);
        }
    }

    /** Lower-case host of a URL; null when missing, localhost or an IP address. */
    private function publicHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim(trim($host, '[]'), '.'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        return $host;
    }

    /** IPv4 as is; IPv6 reduced to its /64 prefix. */
    private function ipBlock(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }
        $packed = inet_pton($ip);

        return $packed === false ? $ip : bin2hex(substr($packed, 0, 8));
    }

    private function allow(string $key, int $perMinute): bool
    {
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    /** @return array{country: string, device: string, browser: string, os: string, language: string} */
    private function dimensions(Request $request, string $userAgent, ?int $width): array
    {
        $country = trim((string) $request->headers->get('CF-IPCountry'));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1 || in_array($country, ['XX', 'T1'], true)) {
            $country = 'unknown';
        }

        $first = explode(',', (string) $request->headers->get('Accept-Language'))[0];
        $language = strtolower(trim((preg_split('/[-_;]/', $first) ?: [''])[0]));
        if (preg_match('/^[a-z]{2,3}$/', $language) !== 1) {
            $language = 'unknown';
        }

        $ua = $this->userAgents->parse($userAgent, $width);

        return ['country' => $country, 'device' => $ua['device'], 'browser' => $ua['browser'], 'os' => $ua['os'], 'language' => $language];
    }
}
