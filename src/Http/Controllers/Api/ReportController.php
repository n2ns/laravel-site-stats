<?php

namespace N2ns\Stats\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use N2ns\Stats\Models\ApiToken;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\Period;
use N2ns\Stats\Reports\StatsQuery;

/** Read-only JSON API, authorised with a Bearer API token (stats:token). */
class ReportController
{
    public const DEFAULT_DAYS = 30;

    public const DEFAULT_LIMIT = 50;

    public const MAX_LIMIT = 500;

    public const MAX_INTEGER = 999999999;

    public const SNAPSHOT_LIMIT = 10;

    public const MAX_SNAPSHOT_LIMIT = 100;

    public function __construct(private StatsQuery $stats) {}

    public function sites(Request $request): JsonResponse
    {
        $token = $this->token($request);
        if ($token instanceof JsonResponse) {
            return $token;
        }

        $sites = Site::query()->orderBy('name')->orderBy('id')->get()
            ->filter(fn (Site $site): bool => $token->canAccess($site))
            ->map(fn (Site $site): array => [
                'id' => $site->public_id,
                'name' => $site->name,
                'domains' => $site->domains,
                'timezone' => $site->timezone,
            ])->values();

        return response()->json(['data' => $sites]);
    }

    public function summary(Request $request, string $publicId): JsonResponse
    {
        $resolved = $this->resolve($request, $publicId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$site, $period] = $resolved;

        return $this->json($site, $period, ['data' => $this->stats->summary($site, $period->from, $period->to)]);
    }

    public function timeseries(Request $request, string $publicId): JsonResponse
    {
        $resolved = $this->resolve($request, $publicId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$site, $period] = $resolved;

        return $this->json($site, $period, ['data' => $this->stats->timeseries($site, $period->from, $period->to)]);
    }

    public function breakdown(Request $request, string $publicId, string $report): JsonResponse
    {
        $resolved = $this->resolve($request, $publicId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$site, $period] = $resolved;

        if (! in_array($report, StatsQuery::REPORTS, true)) {
            return $this->error(404, "Unknown report [{$report}].");
        }

        $limit = $this->integer($request, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        if ($limit instanceof JsonResponse) {
            return $limit;
        }
        $offset = $this->integer($request, 'offset', 0, 0, self::MAX_INTEGER);
        if ($offset instanceof JsonResponse) {
            return $offset;
        }
        $minVisitors = $this->integer($request, 'min_visitors', 0, 0, self::MAX_INTEGER);
        if ($minVisitors instanceof JsonResponse) {
            return $minVisitors;
        }

        // One row more than asked tells whether another page follows.
        $rows = $this->stats->breakdown($site, $report, $period->from, $period->to, $limit + 1, $offset, $minVisitors);

        return $this->json($site, $period, [
            'report' => $report,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => count($rows) > $limit,
            'data' => array_slice($rows, 0, $limit),
        ]);
    }

    public function ai(Request $request, string $publicId): JsonResponse
    {
        $resolved = $this->resolve($request, $publicId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$site, $period] = $resolved;

        return $this->json($site, $period, ['data' => $this->stats->ai($site, $period->from, $period->to)]);
    }

    /** Summary, daily series, AI totals and the top rows of every report in one call. */
    public function snapshot(Request $request, string $publicId): JsonResponse
    {
        $resolved = $this->resolve($request, $publicId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$site, $period] = $resolved;

        $limit = $this->integer($request, 'limit', self::SNAPSHOT_LIMIT, 1, self::MAX_SNAPSHOT_LIMIT);
        if ($limit instanceof JsonResponse) {
            return $limit;
        }
        $minVisitors = $this->integer($request, 'min_visitors', 0, 0, self::MAX_INTEGER);
        if ($minVisitors instanceof JsonResponse) {
            return $minVisitors;
        }

        $reports = [];
        foreach (StatsQuery::REPORTS as $report) {
            $reports[$report] = $this->stats->breakdown($site, $report, $period->from, $period->to, $limit, 0, $minVisitors);
        }

        return $this->json($site, $period, [
            'summary' => $this->stats->summary($site, $period->from, $period->to),
            'timeseries' => $this->stats->timeseries($site, $period->from, $period->to),
            'ai' => $this->stats->ai($site, $period->from, $period->to),
            'reports' => $reports,
        ]);
    }

    /** A non-negative integer query parameter within [$min, $max], or a 422 response. */
    private function integer(Request $request, string $field, int $default, int $min, int $max): int|JsonResponse
    {
        $value = $request->query($field);
        if ($value === null) {
            return $default;
        }
        if (! is_string($value) || preg_match('/^\d{1,9}$/', $value) !== 1 || (int) $value < $min || (int) $value > $max) {
            return $this->invalid($field, "The {$field} must be an integer from {$min} to {$max}.");
        }

        return (int) $value;
    }

    /**
     * Authenticates the token, then resolves the site (404), checks the token's scope (403) and the period (422).
     *
     * @return array{0: Site, 1: Period}|JsonResponse
     */
    private function resolve(Request $request, string $publicId): array|JsonResponse
    {
        $token = $this->token($request);
        if ($token instanceof JsonResponse) {
            return $token;
        }

        $site = Site::query()->where('public_id', $publicId)->first();
        if ($site === null) {
            return $this->error(404, 'Site not found.');
        }
        if (! $token->canAccess($site)) {
            return $this->error(403, 'This token cannot access this site.');
        }

        $preset = $request->query('period');
        if ($preset !== null) {
            if (! is_string($preset) || ! in_array($preset, Period::PRESETS, true)) {
                return $this->invalid('period', 'The period must be one of: '.implode(', ', Period::PRESETS).'.');
            }
            if ($request->query('from') !== null || $request->query('to') !== null) {
                return $this->invalid('period', 'Use either period or from and to, not both.');
            }

            return [$site, Period::preset($preset, $site->timezone)];
        }

        $default = Period::lastDays(self::DEFAULT_DAYS, $site->timezone);
        $dates = ['from' => $default->from->toDateString(), 'to' => $default->to->toDateString()];
        foreach (array_keys($dates) as $field) {
            $value = $request->query($field);
            if ($value === null) {
                continue;
            }
            if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return $this->invalid($field, "The {$field} date must be a valid YYYY-MM-DD date.");
            }
            $dates[$field] = $value;
        }

        try {
            return [$site, Period::parse($dates['from'], $dates['to'])];
        } catch (InvalidArgumentException $e) {
            return $this->invalid('to', $e->getMessage());
        }
    }

    /** @param array<string, mixed> $body */
    private function json(Site $site, Period $period, array $body): JsonResponse
    {
        return response()->json([
            'site' => $site->public_id,
            'from' => $period->from->toDateString(),
            'to' => $period->to->toDateString(),
            'partial' => $period->isPartial(Period::todayIn($site->timezone ?: 'UTC')),
        ] + $body);
    }

    private function token(Request $request): ApiToken|JsonResponse
    {
        $plain = $request->bearerToken();
        $token = $plain === null || $plain === '' ? null : ApiToken::findByPlain($plain);
        if ($token === null) {
            return $this->error(401, 'Missing or invalid API token.')->header('WWW-Authenticate', 'Bearer');
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $token;
    }

    private function error(int $status, string $message): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    private function invalid(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
