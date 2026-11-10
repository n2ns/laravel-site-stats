<?php

namespace N2ns\Stats\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Recording\Recorder;
use N2ns\Stats\Support\PathNormalizer;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * POST api/server-event (design §5.6): Bearer site server key, JSON {site, kind: "ai_fetch", name, path}.
 * 401 for an unknown site or wrong key, 400 for a malformed event, otherwise 204 (also when rate limited).
 */
class ServerEventController
{
    public function __construct(private readonly Recorder $recorder, private readonly PathNormalizer $paths) {}

    public function __invoke(Request $request): Response
    {
        $body = (string) $request->getContent();
        $data = strlen($body) <= 4096 ? json_decode($body, true) : null;
        if (! is_array($data) || ! is_string($data['site'] ?? null)) {
            return new Response('', 400);
        }

        $key = (string) $request->bearerToken();
        $site = Site::query()->where('public_id', strtoupper($data['site']))->first();
        if ($site === null || $key === '' || ! $site->verifiesServerKey($key)) {
            return new Response('', 401);
        }

        $name = $data['name'] ?? null;
        $path = is_string($data['path'] ?? null) ? $this->paths->normalize($data['path']) : null;
        if (($data['kind'] ?? null) !== 'ai_fetch' || ! is_string($name) || preg_match('/^[a-z0-9_.-]{1,64}$/', $name) !== 1 || $path === null) {
            return new Response('', 400);
        }

        $limiter = 'stats-server-event:'.$site->id;
        if (RateLimiter::tooManyAttempts($limiter, (int) config('stats.rate_limits.server_events_per_minute', 600))) {
            return new Response('', 204);
        }
        RateLimiter::hit($limiter, 60);

        try {
            $this->recorder->recordEvent($site, null, CarbonImmutable::now(), 'ai_fetch', $name, null, $path);
        } catch (Throwable $e) {
            Log::warning('N2N Stats: failed to record a server event', ['exception' => $e::class]);
        }

        return new Response('', 204);
    }
}
