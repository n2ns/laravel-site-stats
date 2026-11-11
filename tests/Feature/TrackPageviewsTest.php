<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use N2ns\Stats\Http\Middleware\TrackPageviews;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Stats;
use N2ns\Stats\Tracking\ServerTracker;

const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

beforeEach(function () {
    Route::middleware(TrackPageviews::class)->group(function () {
        Route::get('/', fn () => '<h1>Home</h1>');
        Route::get('/docs/page', fn () => '<h1>Docs</h1>');
        Route::get('/post', function () {
            Stats::page('A post', 'post', 'Guides');

            return '<h1>Post</h1>';
        });
        Route::get('/moved', fn () => redirect('/docs/page'));
        Route::get('/missing', fn () => abort(404));
        Route::get('/data', fn () => ['ok' => true]);
        Route::get('/admin/users', fn () => '<h1>Admin</h1>');
        Route::post('/docs/page', fn () => '<h1>Docs</h1>');
        Route::post('/checkout', function () {
            Stats::event('checkout', 'Pro plan', 'https://pay.example.net/session');

            return redirect('https://pay.example.net/session');
        });
    });
    Route::post('/plain-checkout', function () {
        Stats::event('checkout');

        return response()->noContent();
    });
    $this->site = Site::create(['name' => 'Example', 'domains' => ['example.com'], 'exclude_paths' => ['/admin*']]);
    $this->browser = ['User-Agent' => BROWSER_UA, 'Accept-Language' => 'fr-FR,fr;q=0.9', 'CF-IPCountry' => 'FR'];
});

it('counts successful html pages in host mode, with the details from Stats::page', function () {
    $this->get('https://www.example.com/post?utm_source=newsletter&token=secret', $this->browser + ['Referer' => 'https://news.example.org/'])->assertOk();
    $this->get('https://example.com/docs/page', $this->browser)->assertOk();

    $pages = DB::table('stats_daily_pages')->orderBy('path')->get();
    expect($pages)->toHaveCount(2)
        ->and([$pages[0]->path, $pages[0]->title, (int) $pages[0]->pageviews])->toBe(['/docs/page', null, 1])
        ->and([$pages[1]->path, $pages[1]->title, $pages[1]->page_type, $pages[1]->content_group])->toBe(['/post', 'A post', 'post', 'Guides'])
        ->and(DB::table('stats_daily_sources')->value('source'))->toBe('newsletter')
        ->and((int) DB::table('stats_daily_totals')->value('visits'))->toBe(1);
    $dimensions = json_decode((string) DB::table('stats_visits')->value('dimensions'), true);
    expect($dimensions)->toMatchArray(['country' => 'FR', 'language' => 'fr', 'browser' => 'Chrome']);
});

it('does not count the package dashboard', function () {
    Route::middleware(TrackPageviews::class)->get('/stats-like', fn () => '<h1>Stats</h1>')->name('stats.fake');
    $this->get('https://example.com/stats-like', $this->browser)->assertOk();

    expect(DB::table('stats_daily_pages')->count())->toBe(0);
});

it('counts the site root', function () {
    $this->get('https://example.com', $this->browser)->assertOk();

    expect(DB::table('stats_daily_pages')->value('path'))->toBe('/');
});

it('does not count redirects, errors, json, other methods, bots, prefetches, excluded paths or other hosts', function () {
    $this->get('https://example.com/moved', $this->browser)->assertRedirect();
    $this->get('https://example.com/missing', $this->browser)->assertNotFound();
    $this->get('https://example.com/data', $this->browser)->assertOk();
    $this->post('https://example.com/docs/page', [], $this->browser)->assertOk();
    $this->get('https://example.com/docs/page', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertOk();
    $this->get('https://example.com/docs/page', ['User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.2)'])->assertOk();
    $this->get('https://example.com/docs/page', ['User-Agent' => ''])->assertOk();
    $this->get('https://example.com/docs/page', $this->browser + ['Sec-Purpose' => 'prefetch'])->assertOk();
    $this->get('https://example.com/docs/page', $this->browser + ['Sec-Purpose' => 'prefetch;prerender'])->assertOk();
    $this->get('https://example.com/docs/page', $this->browser + ['Purpose' => 'prefetch'])->assertOk();
    $this->get('https://example.com/admin/users', $this->browser)->assertOk();
    $this->get('https://other.org/docs/page', $this->browser)->assertOk();

    expect(DB::table('stats_daily_pages')->count())->toBe(0)
        ->and(DB::table('stats_daily_totals')->count())->toBe(0);
});

it('uses the pinned host site instead of the Host header', function () {
    $other = Site::create(['name' => 'Other', 'domains' => ['example.com']]);
    config(['stats.host_site' => $other->public_id]);

    $this->get('https://example.com/docs/page', $this->browser)->assertOk();

    expect(DB::table('stats_daily_pages')->pluck('site_id')->all())->toBe([$other->id]);
});

it('ignores an invalid page type and cuts a long title', function () {
    Route::middleware(TrackPageviews::class)->get('/long', function () {
        Stats::page(str_repeat('t', 250), 'Not Valid', "Group\nName");

        return '<p>long</p>';
    });

    $this->get('https://example.com/long', $this->browser)->assertOk();

    $page = DB::table('stats_daily_pages')->sole();
    expect([mb_strlen((string) $page->title), $page->page_type, $page->content_group])->toBe([200, null, 'Group Name']);
});

it('records Stats::event for the visitor after the response in host mode', function () {
    $this->get('https://example.com/docs/page', $this->browser)->assertOk();
    $this->post('https://example.com/checkout', [], $this->browser)->assertRedirect('https://pay.example.net/session');
    $this->post('https://example.com/plain-checkout', [], $this->browser)->assertNoContent();

    $events = DB::table('stats_daily_events')->orderBy('path')->get();
    expect($events)->toHaveCount(2)
        ->and([$events[0]->kind, $events[0]->name, $events[0]->label, $events[0]->target, $events[0]->path, (int) $events[0]->count, (int) $events[0]->visitors])
        ->toBe(['custom', 'checkout', 'Pro plan', 'https://pay.example.net/session', '/checkout', 1, 1])
        ->and([$events[1]->name, $events[1]->label, $events[1]->path])->toBe(['checkout', null, '/plain-checkout'])
        ->and((int) DB::table('stats_daily_totals')->value('events'))->toBe(2);
});

it('does not record Stats::event for bots or invalid names', function () {
    Route::post('/bad-event', function () {
        Stats::event('Bad Name');

        return response()->noContent();
    });
    $this->post('https://example.com/checkout', [], ['User-Agent' => 'curl/8.0'])->assertRedirect();
    $this->post('https://example.com/bad-event', [], $this->browser)->assertNoContent();

    expect(DB::table('stats_daily_events')->count())->toBe(0);
});

it('forwards pageviews to the host in client mode', function () {
    config(['stats.mode' => 'client', 'stats.client.endpoint' => 'https://stats.example.net/', 'stats.client.site' => 'SITEID', 'stats.client.key' => 'client-key']);
    Http::fake();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->get('https://client.test/post?gclid=abc&email=a@b.c', $this->browser + ['Referer' => 'https://www.google.com/'])->assertOk();
    $this->get('https://client.test/missing', $this->browser)->assertNotFound();
    $this->get('https://client.test/docs/page', ['User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0)'])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://stats.example.net/api/server-event'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer client-key')
        && $request->data() === [
            'site' => 'SITEID', 'kind' => 'pageview', 'url' => 'https://client.test/post?gclid=abc',
            'referrer' => 'https://www.google.com/', 'title' => 'A post', 'page_type' => 'post', 'content_group' => 'Guides',
            'ip' => '203.0.113.5', 'user_agent' => BROWSER_UA, 'country' => 'FR', 'accept_language' => 'fr-FR,fr;q=0.9',
        ]);
    expect(DB::table('stats_daily_pages')->count())->toBe(0);
});

it('forwards Stats::event to the host in client mode', function () {
    config(['stats.mode' => 'client', 'stats.client.endpoint' => 'https://stats.example.net', 'stats.client.site' => 'SITEID', 'stats.client.key' => 'client-key']);
    Http::fake();

    $this->post('https://client.test/checkout?utm_campaign=spring', [], ['User-Agent' => BROWSER_UA])->assertRedirect();

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://stats.example.net/api/server-event'
        && $request->data() === [
            'site' => 'SITEID', 'kind' => 'event', 'url' => 'https://client.test/checkout?utm_campaign=spring',
            'name' => 'checkout', 'label' => 'Pro plan', 'target' => 'https://pay.example.net/session',
            'ip' => '127.0.0.1', 'user_agent' => BROWSER_UA, 'country' => null, 'accept_language' => 'en-us,en;q=0.5', // test request default
        ]);
});

it('swallows client errors and skips sending without configuration', function () {
    config(['stats.mode' => 'client', 'stats.client.endpoint' => 'https://stats.example.net', 'stats.client.site' => 'SITEID', 'stats.client.key' => 'k']);
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $this->get('https://client.test/docs/page', $this->browser)->assertOk();
    $this->post('https://client.test/checkout', [], $this->browser)->assertRedirect();

    config(['stats.client.key' => null]);
    Http::fake();
    $this->get('https://client.test/docs/page', $this->browser)->assertOk();
    Http::assertNothingSent();
});

it('counts each Stats::event once when the app handles several requests', function () {
    $this->post('https://example.com/plain-checkout', [], $this->browser)->assertNoContent();
    $this->get('https://example.com/docs/page', $this->browser)->assertOk();
    $this->get('https://example.com/docs/page', $this->browser)->assertOk();

    expect((int) DB::table('stats_daily_events')->value('count'))->toBe(1);
});

it('keeps only the attribution query parameters in the page URL', function () {
    $request = Request::create('https://example.com/a/b?utm_source=x&UTM_Medium=y&token=t&fbclid=1&q=&li_fat_id=2');

    expect(ServerTracker::pageUrl($request))->toBe('https://example.com/a/b?utm_source=x&UTM_Medium=y&fbclid=1&li_fat_id=2');
});

it('ignores a referrer that is not valid UTF-8 or has control characters', function (string $referrer) {
    $this->get('https://example.com/docs/page', $this->browser + ['Referer' => $referrer])->assertOk();

    expect(DB::table('stats_daily_sources')->pluck('source_type')->all())->toBe(['direct'])
        ->and((int) DB::table('stats_daily_totals')->value('pageviews'))->toBe(1);
})->with([
    'invalid utf-8' => ["https://ex\xffample.org/"],
    'control character' => ["https://example.org/\x01"],
]);
