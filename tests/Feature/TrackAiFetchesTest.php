<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use N2ns\Stats\Http\Middleware\TrackAiFetches;
use N2ns\Stats\Models\Site;

const GPTBOT_UA = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)';

beforeEach(function () {
    Route::middleware(TrackAiFetches::class)->group(function () {
        Route::get('/docs/page', fn () => 'ok');
        Route::get('/moved', fn () => redirect('/docs/page'));
        Route::get('/missing', fn () => abort(404));
        Route::post('/docs/page', fn () => 'ok');
    });
    $this->site = Site::create(['name' => 'Example', 'domains' => ['example.com']]);
});

it('records ai fetches of the matching site in host mode', function () {
    $this->get('https://www.example.com/docs/page?x=1', ['User-Agent' => GPTBOT_UA])->assertOk();
    $this->get('https://example.com/moved', ['User-Agent' => GPTBOT_UA])->assertRedirect();

    $events = DB::table('stats_daily_events')->orderBy('path')->get();
    expect($events)->toHaveCount(2)
        ->and([$events[0]->kind, $events[0]->name, $events[0]->path, (int) $events[0]->count])->toBe(['ai_fetch', 'gptbot', '/docs/page', 1])
        ->and([$events[1]->name, $events[1]->path])->toBe(['gptbot', '/moved']);
});

it('uses the pinned host site instead of the Host header', function () {
    $other = Site::create(['name' => 'Other', 'domains' => ['other.org']]);
    config(['stats.host_site' => $other->public_id]);

    $this->get('https://example.com/docs/page', ['User-Agent' => GPTBOT_UA])->assertOk();

    expect(DB::table('stats_daily_events')->pluck('site_id')->all())->toBe([$other->id]);
});

it('rate limits ai fetches per site in host mode', function () {
    config(['stats.rate_limits.server_events_per_minute' => 2]);
    foreach (range(1, 4) as $i) {
        $this->get('https://example.com/docs/page', ['User-Agent' => GPTBOT_UA])->assertOk();
    }

    expect((int) DB::table('stats_daily_events')->value('count'))->toBe(2);
});

it('ignores other requests in host mode', function () {
    $this->get('https://example.com/docs/page', ['User-Agent' => 'Mozilla/5.0 Chrome/130.0'])->assertOk();
    $this->get('https://example.com/missing', ['User-Agent' => GPTBOT_UA])->assertNotFound();
    $this->post('https://example.com/docs/page', [], ['User-Agent' => GPTBOT_UA])->assertOk();
    $this->get('https://other.com/docs/page', ['User-Agent' => GPTBOT_UA])->assertOk();

    expect(DB::table('stats_daily_events')->count())->toBe(0);
});

it('sends ai fetches to the host in client mode', function () {
    config([
        'stats.mode' => 'client',
        'stats.client.endpoint' => 'https://stats.example.net/',
        'stats.client.site' => 'SITEID',
        'stats.client.key' => 'client-key',
    ]);
    Http::fake();

    $this->get('https://anything.test/docs/page', ['User-Agent' => 'Mozilla/5.0 (compatible; ClaudeBot/1.0)'])->assertOk();
    $this->get('https://anything.test/docs/page', ['User-Agent' => 'Mozilla/5.0 Chrome/130.0'])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://stats.example.net/api/server-event'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer client-key')
        && $request->data() === ['site' => 'SITEID', 'kind' => 'ai_fetch', 'name' => 'claudebot', 'path' => '/docs/page']);
    expect(DB::table('stats_daily_events')->count())->toBe(0);
});

it('swallows client errors and skips sending without configuration', function () {
    config(['stats.mode' => 'client', 'stats.client.endpoint' => 'https://stats.example.net', 'stats.client.site' => 'SITEID', 'stats.client.key' => 'k']);
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $this->get('https://anything.test/docs/page', ['User-Agent' => GPTBOT_UA])->assertOk();

    config(['stats.client.key' => null]);
    Http::fake();
    $this->get('https://anything.test/docs/page', ['User-Agent' => GPTBOT_UA])->assertOk();
    Http::assertNothingSent();
});
