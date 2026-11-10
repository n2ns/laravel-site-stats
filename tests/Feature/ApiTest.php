<?php

use Illuminate\Support\Carbon;
use N2ns\Stats\Models\ApiToken;
use N2ns\Stats\Reports\StatsQuery;
use N2ns\Stats\Tests\StatsSeeder as S;

function apiToken(?array $siteIds = null, string $plain = 'secret-token'): ApiToken
{
    return ApiToken::create(['name' => 't', 'token_hash' => hash('sha256', $plain), 'site_ids' => $siteIds]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-03-31 12:00:00');
    $this->site = S::site('Alpha');
    $this->other = S::site('Beta');
    $this->auth = ['Authorization' => 'Bearer secret-token'];
});

it('requires a valid bearer token', function (array $headers) {
    apiToken();
    $this->getJson('/api/v1/sites', $headers)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer')
        ->assertExactJson(['message' => 'Missing or invalid API token.']);
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/summary', $headers)->assertUnauthorized();
})->with([
    'missing' => [[]],
    'wrong' => [['Authorization' => 'Bearer nope']],
    'not bearer' => [['Authorization' => 'Basic c2VjcmV0LXRva2Vu']],
]);

it('lists the sites the token can access and records its use', function () {
    $token = apiToken([$this->site->id]);

    $this->getJson('/api/v1/sites', $this->auth)
        ->assertOk()
        ->assertExactJson(['data' => [[
            'id' => $this->site->public_id, 'name' => 'Alpha', 'domains' => ['example.com'], 'timezone' => 'UTC',
        ]]]);

    expect($token->refresh()->last_used_at?->toDateTimeString())->toBe('2026-03-31 12:00:00');
});

it('lists all sites for an unscoped token', function () {
    apiToken();
    $this->getJson('/api/v1/sites', $this->auth)->assertOk()->assertJsonCount(2, 'data');
});

it('returns 404 for an unknown site and 403 outside the token scope', function () {
    apiToken([$this->site->id]);
    $this->getJson('/api/v1/sites/01HZZZZZZZZZZZZZZZZZZZZZZZ/summary', $this->auth)->assertNotFound();
    foreach (['summary', 'timeseries', 'breakdown/pages'] as $endpoint) {
        $this->getJson('/api/v1/sites/'.$this->other->public_id.'/'.$endpoint, $this->auth)->assertForbidden();
    }
});

it('returns the summary with the previous period', function () {
    apiToken();
    S::totals($this->site, '2026-03-02', ['visitors' => 4, 'visits' => 5, 'pageviews' => 10, 'bounces' => 1, 'duration_seconds' => 100]);
    S::totals($this->site, '2026-03-01', ['visitors' => 2, 'visits' => 2, 'pageviews' => 2]);

    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/summary?from=2026-03-02&to=2026-03-02', $this->auth)
        ->assertOk()
        ->assertExactJson([
            'site' => $this->site->public_id, 'from' => '2026-03-02', 'to' => '2026-03-02',
            'data' => [
                'visitors' => 4, 'visits' => 5, 'pageviews' => 10, 'views_per_visit' => 2.0, 'bounce_rate' => 0.2,
                'avg_duration_seconds' => 20, 'events' => 0,
                'previous' => ['visitors' => 2, 'visits' => 2, 'pageviews' => 2, 'views_per_visit' => 1.0, 'bounce_rate' => 0.0, 'avg_duration_seconds' => 0, 'events' => 0],
            ],
        ]);
});

it('defaults to the last 30 days in the site timezone', function () {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/timeseries', $this->auth)
        ->assertOk()
        ->assertJsonPath('from', '2026-03-02')
        ->assertJsonPath('to', '2026-03-31')
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('data.0', ['date' => '2026-03-02', 'visitors' => 0, 'visits' => 0, 'pageviews' => 0]);
});

it('returns a breakdown with a limit', function () {
    apiToken();
    S::page($this->site, '2026-03-10', '/a', ['pageviews' => 3, 'visitors' => 2, 'entries' => 1, 'exits' => 1]);
    S::page($this->site, '2026-03-10', '/b', ['pageviews' => 9, 'visitors' => 5, 'entries' => 0, 'exits' => 0]);

    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/pages?limit=1', $this->auth)
        ->assertOk()
        ->assertExactJson([
            'site' => $this->site->public_id, 'report' => 'pages', 'from' => '2026-03-02', 'to' => '2026-03-31',
            'data' => [['path' => '/b', 'pageviews' => 9, 'visitors' => 5, 'entries' => 0, 'exits' => 0]],
        ]);
});

it('serves every report', function (string $report) {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/'.$report, $this->auth)
        ->assertOk()->assertJsonPath('report', $report)->assertJsonPath('data', []);
})->with(StatsQuery::REPORTS);

it('returns 404 for an unknown report', function () {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/secrets', $this->auth)->assertNotFound();
});

it('validates dates and limit', function (string $query, string $field) {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/pages?'.$query, $this->auth)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'bad from' => ['from=2026-02-30', 'from'],
    'bad to format' => ['to=31.03.2026', 'to'],
    'array' => ['from[]=2026-03-01', 'from'],
    'reversed' => ['from=2026-03-10&to=2026-03-01', 'to'],
    'too long' => ['from=2025-01-01&to=2026-03-01', 'to'],
    'limit zero' => ['limit=0', 'limit'],
    'limit too high' => ['limit=501', 'limit'],
    'limit not int' => ['limit=abc', 'limit'],
    'limit negative' => ['limit=-1', 'limit'],
]);

it('accepts the maximum range and limit', function () {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/pages?from=2025-03-31&to=2026-03-31&limit=500', $this->auth)->assertOk();
});
