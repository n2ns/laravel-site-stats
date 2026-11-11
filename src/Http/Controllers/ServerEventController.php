<?php

namespace N2ns\Stats\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use N2ns\Stats\Collector\Client;
use N2ns\Stats\Collector\Collector;
use N2ns\Stats\Collector\Payload;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Recording\Recorder;
use N2ns\Stats\Support\PathNormalizer;
use N2ns\Stats\Tracking\ServerTracker;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * POST api/server-event (design §5.6): Bearer site server key, JSON {site, kind, ...}. Kinds: "ai_fetch" {name, path};
 * "pageview" {url, referrer, title, page_type, content_group} and "event" {url, name, label, target}, both with the
 * visitor's {ip, user_agent, country, accept_language}. 401 for an unknown site or wrong key, 400 for a malformed
 * event, otherwise 204 (also when rate limited).
 */
class ServerEventController
{
    public const MAX_BODY_BYTES = 8192;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly PathNormalizer $paths,
        private readonly Collector $collector,
    ) {}

    public function __invoke(Request $request): Response
    {
        $body = (string) $request->getContent();
        $data = strlen($body) <= self::MAX_BODY_BYTES ? json_decode($body, true) : null;
        if (! is_array($data) || ! is_string($data['site'] ?? null)) {
            return new Response('', 400);
        }

        $key = (string) $request->bearerToken();
        $site = Site::query()->where('public_id', strtoupper($data['site']))->first();
        if ($site === null || $key === '' || ! $site->verifiesServerKey($key)) {
            return new Response('', 401);
        }

        $kind = $data['kind'] ?? null;
        if ($kind === 'ai_fetch') {
            $name = $data['name'] ?? null;
            $path = is_string($data['path'] ?? null) ? $this->paths->normalize($data['path']) : null;
            if (! is_string($name) || preg_match('/^[a-z0-9_.-]{1,64}$/', $name) !== 1 || $path === null) {
                return new Response('', 400);
            }
            // AI fetches have no visitor, so they get a per-site limit of their own.
            $limiter = 'stats-server-event:'.$site->id;
            if (RateLimiter::tooManyAttempts($limiter, (int) config('stats.rate_limits.server_events_per_minute', 600))) {
                return new Response('', 204);
            }
            RateLimiter::hit($limiter, 60);
            $record = fn () => $this->recorder->recordEvent($site, null, CarbonImmutable::now(), 'ai_fetch', $name, null, null, $path);
        } elseif ($kind === 'pageview' || $kind === 'event') {
            $payload = Payload::fromArray(ServerTracker::payloadData($site->public_id, $kind, $data));
            $client = $this->client($data);
            if ($payload === null || $client === null) {
                return new Response('', 400);
            }
            // The collector applies the same per-site, per-IP and per-visitor limits as to browser beacons.
            $record = fn () => $this->collector->collectFrom($payload, $client);
        } else {
            return new Response('', 400);
        }

        try {
            $record();
        } catch (Throwable $e) {
            Log::warning('N2N Stats: failed to record a server event', ['exception' => $e::class]);
        }

        return new Response('', 204);
    }

    /**
     * The visitor the client app forwarded; null when malformed. Origin is null: the client's server sends it.
     *
     * @param  array<mixed>  $data
     */
    private function client(array $data): ?Client
    {
        $ip = $data['ip'] ?? null;
        $userAgent = $data['user_agent'] ?? null;
        $country = $data['country'] ?? null;
        $language = $data['accept_language'] ?? null;
        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false
            || ! is_string($userAgent) || mb_strlen($userAgent) > 512
            || ($country !== null && (! is_string($country) || mb_strlen($country) > 16))
            || ($language !== null && (! is_string($language) || mb_strlen($language) > 256))) {
            return null;
        }

        return new Client($ip, $userAgent, $country, $language);
    }
}
