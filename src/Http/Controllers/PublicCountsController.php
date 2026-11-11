<?php

namespace N2ns\Stats\Http\Controllers;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\StatsQuery;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET api/public/sites/{public_id}/counts?path[]=&event[]=: all-time page views per path and custom event counts per
 * name and label, without a token, for sites with public_counts on. Readable from any origin (no credentials).
 */
class PublicCountsController
{
    public const MAX_PATHS = 50;

    public const MAX_EVENTS = 20;

    private const CORS = ['Access-Control-Allow-Origin' => '*'];

    public function __construct(private readonly StatsQuery $stats) {}

    public function __invoke(Request $request, string $publicId): JsonResponse
    {
        // Keyed with the app key and the date like the collector's limiter, so keys cannot be reversed to an IP.
        $key = 'stats-public-ip:'.hash_hmac('sha256', CarbonImmutable::now()->toDateString().'|'.self::ipBlock((string) $request->ip()), (string) config('app.key'));
        if (RateLimiter::tooManyAttempts($key, (int) config('stats.rate_limits.public_per_minute', 120))) {
            return response()->json(['message' => 'Too many requests.'], 429, self::CORS + ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        $site = Site::query()->where('public_id', $publicId)->first();
        if ($site === null || ! $site->public_counts) {
            return response()->json(['message' => 'Not found.'], 404, self::CORS);
        }

        $paths = $this->list($request, 'path', self::MAX_PATHS, fn (string $p): bool => str_starts_with($p, '/') && strlen($p) <= 512 && mb_check_encoding($p, 'UTF-8') && preg_match('/[\x00-\x1F\x7F]/', $p) !== 1);
        if ($paths instanceof JsonResponse) {
            return $paths;
        }
        $events = $this->list($request, 'event', self::MAX_EVENTS, fn (string $e): bool => preg_match('/^[a-z0-9_.-]{1,64}$/', $e) === 1);
        if ($events instanceof JsonResponse) {
            return $events;
        }

        $counts = $this->stats->publicCounts($site, $paths, $events);

        // Objects even when empty or keyed by numeric-looking names.
        return response()->json(['site' => $site->public_id, 'pages' => $counts['pages'], 'events' => $counts['events']], 200, self::CORS + [
            'Cache-Control' => 'public, max-age=60',
        ], JSON_FORCE_OBJECT);
    }

    /** CORS preflight. */
    public function options(): Response
    {
        return new Response('', 204, self::CORS + ['Access-Control-Allow-Methods' => 'GET', 'Access-Control-Max-Age' => '86400']);
    }

    /**
     * A repeatable query parameter (?path[]=a&path[]=b, or a single ?path=a) as a list of distinct valid values.
     *
     * @param  Closure(string): bool  $valid
     * @return list<string>|JsonResponse
     */
    private function list(Request $request, string $field, int $max, Closure $valid): array|JsonResponse
    {
        $value = $request->query($field);
        $values = is_string($value) ? [$value] : (array) $value;
        if (! array_is_list($values) || count($values) > $max) {
            return $this->invalid($field, "The {$field} parameter must be a list of at most {$max} values.");
        }
        foreach ($values as $item) {
            if (! is_string($item) || ! $valid($item)) {
                return $this->invalid($field, $field === 'path'
                    ? 'Each path must start with / and have at most 512 characters.'
                    : 'Each event name must match [a-z0-9_.-]{1,64}.');
            }
        }

        return array_values(array_unique($values));
    }

    private function invalid(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422, self::CORS);
    }

    /** IPv4 as is; IPv6 reduced to its /64 prefix (as the collector does). */
    private static function ipBlock(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }
        $packed = inet_pton($ip);

        return $packed === false ? $ip : bin2hex(substr($packed, 0, 8));
    }
}
