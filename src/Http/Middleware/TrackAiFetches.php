<?php

namespace N2ns\Stats\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Recording\Recorder;
use N2ns\Stats\Support\BotDetector;
use N2ns\Stats\Support\PathNormalizer;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts AI fetcher requests (design §5.6). Host mode writes via Recorder; client mode POSTs to
 * config('stats.client.endpoint') /api/server-event with the site key after the response is sent.
 */
class TrackAiFetches
{
    public function __construct(
        private readonly BotDetector $bots,
        private readonly PathNormalizer $paths,
        private readonly Recorder $recorder,
    ) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $request->isMethod('GET') || $response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
                return;
            }

            $fetcher = $this->bots->aiFetcher($request->userAgent());
            $path = $this->paths->normalize($request->getPathInfo());
            if ($fetcher === null || $path === null) {
                return;
            }

            match (config('stats.mode')) {
                'host' => $this->record($request->getHost(), $fetcher, $path),
                'client' => $this->send($fetcher, $path),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('N2N Stats: failed to count an AI fetch', ['exception' => $e::class]);
        }
    }

    private function record(string $host, string $fetcher, string $path): void
    {
        // A pinned site does not depend on the Host header, which a client controls when the web server
        // accepts any host name.
        $pinned = config('stats.host_site');
        $site = is_string($pinned) && $pinned !== ''
            ? Site::query()->where('public_id', $pinned)->first()
            : Site::query()->get()->first(fn (Site $site): bool => $site->allowsHost($host));
        if ($site === null) {
            return;
        }

        $key = 'stats-ai-fetch:'.$site->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('stats.rate_limits.server_events_per_minute', 600))) {
            return;
        }
        RateLimiter::hit($key, 60);

        $this->recorder->recordEvent($site, null, CarbonImmutable::now(), 'ai_fetch', $fetcher, null, null, $path);
    }

    private function send(string $fetcher, string $path): void
    {
        $endpoint = (string) config('stats.client.endpoint');
        $site = (string) config('stats.client.site');
        $key = (string) config('stats.client.key');
        if ($endpoint === '' || $site === '' || $key === '') {
            return;
        }

        Http::timeout((int) config('stats.client.timeout_seconds', 2))
            ->connectTimeout((int) config('stats.client.timeout_seconds', 2))
            ->withToken($key)
            ->acceptJson()
            ->post(rtrim($endpoint, '/').'/api/server-event', ['site' => $site, 'kind' => 'ai_fetch', 'name' => $fetcher, 'path' => $path]);
    }
}
