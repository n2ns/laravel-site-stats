<?php

use N2ns\Stats\Tests\StatsSeeder as S;

beforeEach(function () {
    $this->site = S::site('Blog', ['public_counts' => true]);
    $this->url = '/api/public/sites/'.$this->site->public_id.'/counts';
});

it('returns all-time page views and event counts with CORS and caching headers', function () {
    S::page($this->site, '2026-01-02', '/a', ['pageviews' => 3]);
    S::page($this->site, '2026-02-05', '/a', ['pageviews' => 4]);
    S::page($this->site, '2026-02-05', '/c', ['pageviews' => 9]);
    S::event($this->site, '2026-02-05', 'custom', 'like', null, '/a', 5, 0, 'post-a');
    S::event($this->site, '2026-02-06', 'custom', 'like', null, '/b', 2, 0, 'post-a');
    S::event($this->site, '2026-02-06', 'custom', 'like', null, '/b', 1);
    S::event($this->site, '2026-02-06', 'outbound', 'like', 'https://x.com/', '/b', 7);

    $this->getJson($this->url.'?path[]=/a&path[]=/b&event[]=like&event[]=share')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Cache-Control', 'max-age=60, public')
        ->assertExactJson([
            'site' => $this->site->public_id,
            'pages' => ['/a' => ['pageviews' => 7, 'last_date' => '2026-02-05']],
            'events' => ['like' => ['post-a' => 7, '' => 1]],
        ]);
});

it('accepts a single value and returns objects when nothing matches', function () {
    $response = $this->get($this->url.'?path=/a&event=0')->assertOk();

    expect($response->getContent())->toBe('{"site":"'.$this->site->public_id.'","pages":{},"events":{}}');
});

it('returns 404 for an unknown site or one without public counts', function () {
    $hidden = S::site('Hidden');
    $this->getJson('/api/public/sites/'.$hidden->public_id.'/counts?path[]=/a')->assertNotFound()->assertHeader('Access-Control-Allow-Origin', '*');
    $this->getJson('/api/public/sites/01HZZZZZZZZZZZZZZZZZZZZZZZ/counts')->assertNotFound();
});

it('needs no token and sets no cookies', function () {
    $response = $this->getJson($this->url.'?path[]=/a', ['Authorization' => 'Bearer whatever'])->assertOk();

    expect($response->headers->getCookies())->toBe([]);
});

it('validates paths and event names', function (string $query, string $field) {
    $this->getJson($this->url.'?'.$query)
        ->assertUnprocessable()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonValidationErrors([$field]);
})->with([
    'relative path' => ['path[]=a', 'path'],
    'long path' => ['path[]=/'.str_repeat('a', 512), 'path'],
    'too many paths' => [implode('&', array_map(fn ($i) => 'path[]=/p'.$i, range(1, 51))), 'path'],
    'nested path' => ['path[a][]=/x', 'path'],
    'bad event' => ['event[]=Like', 'event'],
    'long event' => ['event[]='.str_repeat('e', 65), 'event'],
    'too many events' => [implode('&', array_map(fn ($i) => 'event[]=e'.$i, range(1, 21))), 'event'],
]);

it('accepts the maximum number of paths and events', function () {
    $query = implode('&', array_map(fn ($i) => 'path[]=/p'.$i, range(1, 50))).'&'.implode('&', array_map(fn ($i) => 'event[]=e'.$i, range(1, 20)));
    $this->getJson($this->url.'?'.$query)->assertOk();
});

it('answers the CORS preflight', function () {
    $this->call('OPTIONS', $this->url, [], [], [], ['HTTP_ORIGIN' => 'https://blog.example.org', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'GET');
});

it('rate limits per IP', function () {
    config(['stats.rate_limits.public_per_minute' => 2]);
    $this->getJson($this->url)->assertOk();
    $this->getJson($this->url)->assertOk();
    $this->getJson($this->url)->assertStatus(429)->assertHeader('Access-Control-Allow-Origin', '*');

    $this->getJson($this->url, ['REMOTE_ADDR' => '198.51.100.7'])->assertOk();
});

it('counts IPv6 addresses per /64', function () {
    config(['stats.rate_limits.public_per_minute' => 1]);
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::1'])->getJson($this->url)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::ffff'])->getJson($this->url)->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])->getJson($this->url)->assertOk();
});
