<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use N2ns\Stats\Models\Site;

function serverEvent(array $body, ?string $key = 'secret-key'): TestResponse
{
    $headers = ['CONTENT_TYPE' => 'application/json'];
    if ($key !== null) {
        $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$key;
    }

    return test()->call('POST', '/api/server-event', [], [], [], $headers, json_encode($body));
}

beforeEach(function () {
    $this->site = Site::create(['name' => 'Client', 'domains' => ['client.com'], 'server_key_hash' => hash('sha256', 'secret-key')]);
    $this->body = ['site' => $this->site->public_id, 'kind' => 'ai_fetch', 'name' => 'gptbot', 'path' => '/docs/a'];
});

it('records an ai fetch', function () {
    serverEvent($this->body)->assertNoContent();
    serverEvent(array_merge($this->body, ['site' => strtolower($this->site->public_id)]))->assertNoContent();

    $event = DB::table('stats_daily_events')->first();
    expect([$event->kind, $event->name, $event->path, (int) $event->count, (int) $event->visitors])
        ->toBe(['ai_fetch', 'gptbot', '/docs/a', 2, 0]);
});

it('rejects bad credentials with 401', function () {
    serverEvent($this->body, 'wrong')->assertStatus(401);
    serverEvent($this->body, null)->assertStatus(401);
    serverEvent(array_merge($this->body, ['site' => '01JCZ0000000000000000000AB']))->assertStatus(401);

    $keyless = Site::create(['name' => 'No key', 'domains' => ['x.com']]);
    serverEvent(array_merge($this->body, ['site' => $keyless->public_id]), '')->assertStatus(401);

    expect(DB::table('stats_daily_events')->count())->toBe(0);
});

it('rejects malformed events with 400', function (array $override) {
    serverEvent(array_merge($this->body, $override))->assertStatus(400);
    expect(DB::table('stats_daily_events')->count())->toBe(0);
})->with([
    'other kind' => [['kind' => 'pageview']],
    'bad name' => [['name' => 'GPT Bot']],
    'missing name' => [['name' => null]],
    'bad path' => [['path' => 'docs']],
    'site not a string' => [['site' => 5]],
]);

it('rejects a non-json body with 400', function () {
    test()->call('POST', '/api/server-event', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer secret-key'], 'nope')->assertStatus(400);
});

it('rate limits per site and still answers 204', function () {
    config(['stats.rate_limits.server_events_per_minute' => 2]);
    foreach (range(1, 3) as $i) {
        serverEvent($this->body)->assertNoContent();
    }
    expect((int) DB::table('stats_daily_events')->value('count'))->toBe(2);
});
