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
    'unknown kind' => [['kind' => 'click']],
    'missing kind' => [['kind' => null]],
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

const VISITOR = ['ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0', 'country' => 'DE', 'accept_language' => 'de-DE,de;q=0.9'];

it('records a forwarded pageview with its page details and visitor', function () {
    serverEvent(['site' => $this->site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/docs/a?utm_source=news',
        'referrer' => null, 'title' => 'Docs A', 'page_type' => 'doc', 'content_group' => 'Guides'] + VISITOR)->assertNoContent();

    $page = DB::table('stats_daily_pages')->sole();
    expect([$page->path, $page->title, $page->page_type, $page->content_group, (int) $page->pageviews])->toBe(['/docs/a', 'Docs A', 'doc', 'Guides', 1])
        ->and(DB::table('stats_daily_sources')->value('source'))->toBe('news')
        ->and(DB::table('stats_visits')->count())->toBe(1);
    $country = DB::table('stats_visits')->value('dimensions');
    expect(json_decode((string) $country, true))->toMatchArray(['country' => 'DE', 'language' => 'de', 'browser' => 'Chrome']);
});

it('records a forwarded custom event for the visitor', function () {
    serverEvent(['site' => $this->site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/pricing'] + VISITOR)->assertNoContent();
    serverEvent(['site' => $this->site->public_id, 'kind' => 'event', 'url' => 'https://client.com/pricing', 'name' => 'checkout',
        'label' => 'Pro', 'target' => 'https://pay.example.net/c/1'] + VISITOR)->assertNoContent();

    $event = DB::table('stats_daily_events')->sole();
    expect([$event->kind, $event->name, $event->label, $event->target, $event->path, (int) $event->count, (int) $event->visitors])
        ->toBe(['custom', 'checkout', 'Pro', 'https://pay.example.net/c/1', '/pricing', 1, 1])
        ->and((int) DB::table('stats_daily_totals')->value('events'))->toBe(1);
});

it('drops forwarded pageviews the collector rejects', function (array $override) {
    serverEvent(array_merge(['site' => $this->site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/a'] + VISITOR, $override))->assertNoContent();

    expect(DB::table('stats_daily_pages')->count())->toBe(0);
})->with([
    'other host' => [['url' => 'https://elsewhere.com/a']],
    'bot' => [['user_agent' => 'Googlebot/2.1']],
    'empty user agent' => [['user_agent' => '']],
]);

it('drops forwarded pageviews of excluded paths', function () {
    $this->site->update(['exclude_paths' => ['/admin*']]);
    serverEvent(['site' => $this->site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/admin/users'] + VISITOR)->assertNoContent();

    expect(DB::table('stats_daily_pages')->count())->toBe(0);
});

it('rejects malformed pageviews and events with 400', function (array $body) {
    serverEvent(['site' => $this->site->public_id] + $body)->assertStatus(400);

    expect(DB::table('stats_daily_pages')->count() + DB::table('stats_daily_events')->count())->toBe(0);
})->with([
    'pageview without url' => [['kind' => 'pageview'] + VISITOR],
    'pageview bad url' => [['kind' => 'pageview', 'url' => '/a'] + VISITOR],
    'pageview long title' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'title' => str_repeat('x', 201)] + VISITOR],
    'pageview bad type' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'page_type' => 'Blog Post'] + VISITOR],
    'pageview title not a string' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'title' => 5] + VISITOR],
    'event without name' => [['kind' => 'event', 'url' => 'https://client.com/a'] + VISITOR],
    'event bad name' => [['kind' => 'event', 'url' => 'https://client.com/a', 'name' => 'Check out'] + VISITOR],
    'event bad target' => [['kind' => 'event', 'url' => 'https://client.com/a', 'name' => 'x', 'target' => 'javascript:alert(1)'] + VISITOR],
    'missing ip' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'ip' => null] + VISITOR],
    'bad ip' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'ip' => 'localhost'] + VISITOR],
    'missing user agent' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'user_agent' => null] + VISITOR],
    'long user agent' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'user_agent' => str_repeat('a', 513)] + VISITOR],
    'country not a string' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'country' => ['DE']] + VISITOR],
    'long accept language' => [['kind' => 'pageview', 'url' => 'https://client.com/a', 'accept_language' => str_repeat('a', 257)] + VISITOR],
]);

it('authenticates pageviews and events like ai fetches', function () {
    $body = ['site' => $this->site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/a'] + VISITOR;
    serverEvent($body, 'wrong')->assertStatus(401);
    serverEvent($body, null)->assertStatus(401);

    expect(DB::table('stats_daily_pages')->count())->toBe(0);
});

it('accepts bodies up to 8 KB', function () {
    $base = ['site' => $this->site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/a', 'title' => str_repeat('é', 200),
        'content_group' => str_repeat('ü', 120), 'referrer' => 'https://ref.example.org/'.str_repeat('r', 2000)] + VISITOR;
    expect(strlen((string) json_encode($base)))->toBeGreaterThan(4096)->toBeLessThanOrEqual(8192);
    serverEvent($base)->assertNoContent();
    expect(DB::table('stats_daily_pages')->value('title'))->toBe(str_repeat('é', 200));

    serverEvent($base + ['padding' => str_repeat('p', 8192)])->assertStatus(400);
});

it('limits forwarded pageviews like browser beacons, not by the AI fetch limit', function () {
    config(['stats.rate_limits.server_events_per_minute' => 2, 'stats.rate_limits.per_site_per_minute' => 4]);
    foreach (range(1, 6) as $i) {
        serverEvent(['site' => $this->site->public_id, 'kind' => 'pageview', 'url' => 'https://client.com/a'] + VISITOR)->assertNoContent();
    }

    expect((int) DB::table('stats_daily_pages')->value('pageviews'))->toBe(4);
});
