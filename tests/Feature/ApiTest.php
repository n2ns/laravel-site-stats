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
    foreach (['summary', 'timeseries', 'breakdown/pages', 'ai', 'snapshot'] as $endpoint) {
        $this->getJson('/api/v1/sites/'.$this->other->public_id.'/'.$endpoint, $this->auth)->assertForbidden();
    }
});

it('returns the summary with the complete-day comparison', function () {
    apiToken();
    S::totals($this->site, '2026-03-02', ['visitors' => 4, 'visits' => 5, 'pageviews' => 10, 'bounces' => 1, 'duration_seconds' => 100, 'outbound' => 3]);
    S::totals($this->site, '2026-03-01', ['visitors' => 2, 'visits' => 2, 'pageviews' => 2]);
    S::page($this->site, '2026-03-02', '/a', ['pageviews' => 10]);

    $current = ['visitors' => 4, 'visits' => 5, 'pageviews' => 10, 'pages' => 1, 'views_per_visit' => 2.0, 'bounce_rate' => 0.2, 'avg_duration_seconds' => 20, 'avg_page_seconds' => 0, 'events' => 0, 'outbound' => 3];
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/summary?from=2026-03-02&to=2026-03-02', $this->auth)
        ->assertOk()
        ->assertExactJson([
            'site' => $this->site->public_id, 'from' => '2026-03-02', 'to' => '2026-03-02', 'partial' => false,
            'data' => $current + [
                'partial' => false,
                'comparison' => [
                    'current_from' => '2026-03-02', 'current_to' => '2026-03-02', 'previous_from' => '2026-03-01', 'previous_to' => '2026-03-01',
                    'current' => $current,
                    'previous' => ['visitors' => 2, 'visits' => 2, 'pageviews' => 2, 'pages' => 0, 'views_per_visit' => 1.0, 'bounce_rate' => 0.0, 'avg_duration_seconds' => 0, 'avg_page_seconds' => 0, 'events' => 0, 'outbound' => 0],
                ],
            ],
        ]);
});

it('marks a range that includes the site\'s today as partial', function (string $query, bool $partial) {
    apiToken();
    foreach (['summary', 'timeseries', 'breakdown/pages', 'ai', 'snapshot'] as $endpoint) {
        $this->getJson('/api/v1/sites/'.$this->site->public_id.'/'.$endpoint.'?'.$query, $this->auth)
            ->assertOk()->assertJsonPath('partial', $partial);
    }
})->with([
    'today' => ['period=today', true],
    '15d' => ['period=15d', true],
    'yesterday' => ['period=yesterday', false],
    'past dates' => ['from=2026-03-01&to=2026-03-30', false],
]);

it('uses the site timezone for the partial flag', function () {
    apiToken();
    Carbon::setTestNow('2026-03-31 23:30:00');
    $tokyo = S::site('Tokyo', ['timezone' => 'Asia/Tokyo']); // already 2026-04-01 there

    $this->getJson('/api/v1/sites/'.$tokyo->public_id.'/summary?from=2026-03-25&to=2026-03-31', $this->auth)->assertJsonPath('partial', false);
    $this->getJson('/api/v1/sites/'.$tokyo->public_id.'/summary?period=today', $this->auth)
        ->assertJsonPath('from', '2026-04-01')->assertJsonPath('partial', true);
});

it('defaults to the last 30 days in the site timezone', function () {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/timeseries', $this->auth)
        ->assertOk()
        ->assertJsonPath('from', '2026-03-02')
        ->assertJsonPath('to', '2026-03-31')
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('data.0', ['date' => '2026-03-02', 'visitors' => 0, 'visits' => 0, 'pageviews' => 0, 'events' => 0, 'outbound' => 0, 'pages' => 0, 'views_per_visit' => 0, 'bounce_rate' => 0, 'avg_duration_seconds' => 0, 'avg_page_seconds' => 0]);
});

it('returns a breakdown with a limit', function () {
    apiToken();
    S::page($this->site, '2026-03-10', '/a', ['pageviews' => 3, 'visitors' => 2, 'entries' => 1, 'exits' => 1]);
    S::page($this->site, '2026-03-10', '/b', ['pageviews' => 9, 'visitors' => 5, 'entries' => 0, 'exits' => 0, 'title' => 'B', 'page_type' => 'post']);

    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/pages?limit=1', $this->auth)
        ->assertOk()
        ->assertExactJson([
            'site' => $this->site->public_id, 'report' => 'pages', 'from' => '2026-03-02', 'to' => '2026-03-31', 'partial' => true,
            'offset' => 0, 'limit' => 1, 'has_more' => true,
            'data' => [['path' => '/b', 'title' => 'B', 'page_type' => 'post', 'pageviews' => 9, 'visitors' => 5, 'entries' => 0, 'exits' => 0]],
        ]);
});

it('pages through a breakdown with offset and has_more', function () {
    apiToken();
    foreach (range(1, 5) as $i) {
        S::page($this->site, '2026-03-10', '/p'.$i, ['pageviews' => 10 - $i, 'visitors' => 1]);
    }
    $url = '/api/v1/sites/'.$this->site->public_id.'/breakdown/pages?';

    $page = fn (string $query) => $this->getJson($url.$query, $this->auth)->assertOk();
    expect($page('limit=2')->json())->toMatchArray(['offset' => 0, 'limit' => 2, 'has_more' => true])
        ->and(array_column($page('limit=2')->json('data'), 'path'))->toBe(['/p1', '/p2'])
        ->and(array_column($page('limit=2&offset=2')->json('data'), 'path'))->toBe(['/p3', '/p4'])
        ->and($page('limit=2&offset=2')->json('has_more'))->toBeTrue()
        ->and(array_column($page('limit=2&offset=4')->json('data'), 'path'))->toBe(['/p5'])
        ->and($page('limit=2&offset=4')->json('has_more'))->toBeFalse()
        ->and($page('limit=5')->json('has_more'))->toBeFalse()
        ->and($page('offset=9')->json())->toMatchArray(['offset' => 9, 'limit' => 50, 'has_more' => false, 'data' => []]);
});

it('drops rows below min_visitors', function () {
    apiToken();
    S::page($this->site, '2026-03-10', '/big', ['pageviews' => 9, 'visitors' => 5]);
    S::page($this->site, '2026-03-10', '/small', ['pageviews' => 8, 'visitors' => 2]);
    S::page($this->site, '2026-03-11', '/small', ['pageviews' => 1, 'visitors' => 1]);

    $paths = fn (int $min) => array_column($this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/pages?min_visitors='.$min, $this->auth)->assertOk()->json('data'), 'path');
    expect($paths(0))->toBe(['/big', '/small'])
        ->and($paths(3))->toBe(['/big', '/small'])
        ->and($paths(4))->toBe(['/big'])
        ->and($paths(6))->toBe([]);
});

it('returns the ai totals and series', function () {
    apiToken();
    S::source($this->site, '2026-03-30', 'ai', 'chatgpt.com', ['visits' => 3, 'visitors' => 2, 'pageviews' => 5]);
    S::event($this->site, '2026-03-31', 'ai_fetch', 'gptbot', null, '/a', 4);

    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/ai?from=2026-03-30&to=2026-03-31', $this->auth)
        ->assertOk()
        ->assertExactJson([
            'site' => $this->site->public_id, 'from' => '2026-03-30', 'to' => '2026-03-31', 'partial' => true,
            'data' => [
                'visits' => 3, 'visitors' => 2, 'pageviews' => 5, 'fetches' => 4,
                'series' => [
                    ['date' => '2026-03-30', 'visits' => 3, 'visitors' => 2, 'fetches' => 0],
                    ['date' => '2026-03-31', 'visits' => 0, 'visitors' => 0, 'fetches' => 4],
                ],
            ],
        ]);
});

it('returns a snapshot of everything in one call', function () {
    apiToken();
    S::totals($this->site, '2026-03-30', ['visitors' => 3, 'visits' => 3, 'pageviews' => 4]);
    foreach (range(1, 12) as $i) {
        S::page($this->site, '2026-03-30', '/p'.$i, ['pageviews' => 20 - $i, 'visitors' => $i]);
    }

    $response = $this->getJson('/api/v1/sites/'.$this->site->public_id.'/snapshot?period=7d', $this->auth)->assertOk();

    expect(array_keys($response->json()))->toBe(['site', 'from', 'to', 'partial', 'summary', 'timeseries', 'ai', 'reports'])
        ->and($response->json('from'))->toBe('2026-03-25')
        ->and($response->json('summary.pageviews'))->toBe(4)
        ->and($response->json('timeseries'))->toHaveCount(7)
        ->and($response->json('ai.fetches'))->toBe(0)
        ->and(array_keys($response->json('reports')))->toBe(StatsQuery::REPORTS)
        ->and($response->json('reports.pages'))->toHaveCount(10)
        ->and($response->json('reports.pages.0.path'))->toBe('/p1');

    $limited = $this->getJson('/api/v1/sites/'.$this->site->public_id.'/snapshot?period=7d&limit=3&min_visitors=11', $this->auth)->assertOk();
    expect(array_column($limited->json('reports.pages'), 'path'))->toBe(['/p11', '/p12']);
});

it('checks access for the ai and snapshot endpoints', function () {
    apiToken([$this->site->id]);
    $this->getJson('/api/v1/sites/'.$this->other->public_id.'/ai', $this->auth)->assertForbidden();
    $this->getJson('/api/v1/sites/'.$this->other->public_id.'/snapshot', $this->auth)->assertForbidden();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/snapshot', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
});

it('validates the snapshot parameters', function (string $query, string $field) {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/snapshot?'.$query, $this->auth)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'limit zero' => ['limit=0', 'limit'],
    'limit too high' => ['limit=101', 'limit'],
    'min_visitors negative' => ['min_visitors=-1', 'min_visitors'],
    'bad period' => ['period=1y', 'period'],
]);

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
    'offset negative' => ['offset=-1', 'offset'],
    'offset not int' => ['offset=1.5', 'offset'],
    'offset array' => ['offset[]=1', 'offset'],
    'offset too long' => ['offset=1234567890', 'offset'],
    'min_visitors negative' => ['min_visitors=-2', 'min_visitors'],
    'min_visitors not int' => ['min_visitors=x', 'min_visitors'],
]);

it('accepts the maximum range and limit', function () {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/breakdown/pages?from=2025-03-31&to=2026-03-31&limit=500', $this->auth)->assertOk();
});

it('accepts a preset period in the site timezone', function (string $period, string $from, string $to) {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/timeseries?period='.$period, $this->auth)
        ->assertOk()
        ->assertJsonPath('from', $from)
        ->assertJsonPath('to', $to);
})->with([
    'today' => ['today', '2026-03-31', '2026-03-31'],
    'yesterday' => ['yesterday', '2026-03-30', '2026-03-30'],
    '7d' => ['7d', '2026-03-25', '2026-03-31'],
    '15d' => ['15d', '2026-03-17', '2026-03-31'],
    '30d' => ['30d', '2026-03-02', '2026-03-31'],
    '90d' => ['90d', '2026-01-01', '2026-03-31'],
    'month' => ['month', '2026-03-01', '2026-03-31'],
    'last_month' => ['last_month', '2026-02-01', '2026-02-28'],
]);

it('rejects an unknown period or a period combined with dates', function (string $query) {
    apiToken();
    $this->getJson('/api/v1/sites/'.$this->site->public_id.'/summary?'.$query, $this->auth)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['period']);
})->with([
    'unknown' => ['period=1y'],
    'array' => ['period[]=7d'],
    'with from' => ['period=7d&from=2026-03-01'],
    'with to' => ['period=7d&to=2026-03-01'],
]);
