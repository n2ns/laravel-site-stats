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

        $limit = $request->query('limit', (string) self::DEFAULT_LIMIT);
        if (! is_string($limit) || preg_match('/^\d{1,3}$/', $limit) !== 1 || (int) $limit < 1 || (int) $limit > self::MAX_LIMIT) {
            return $this->invalid('limit', 'The limit must be an integer from 1 to '.self::MAX_LIMIT.'.');
        }

        return $this->json($site, $period, [
            'report' => $report,
            'data' => $this->stats->breakdown($site, $report, $period->from, $period->to, (int) $limit),
        ]);
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
        return response()->json(['site' => $site->public_id, 'from' => $period->from->toDateString(), 'to' => $period->to->toDateString()] + $body);
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
