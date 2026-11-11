<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Recording\Recorder;

const RECORD_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

function recHit(Site $site, string $type, string $url, array $extra = [], string $ip = '198.51.100.7'): void
{
    test()->call('POST', '/api/event', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
        'HTTP_ORIGIN' => 'https://example.com',
        'HTTP_USER_AGENT' => RECORD_UA,
        'REMOTE_ADDR' => $ip,
    ], json_encode(array_merge(['s' => $site->public_id, 't' => $type, 'u' => $url], $extra)))->assertNoContent();
}

function recTotals(): array
{
    $row = DB::table('stats_daily_totals')->first();

    return $row === null ? [] : array_map('intval', array_diff_key((array) $row, ['id' => 1, 'site_id' => 1, 'date' => 1]));
}

function recPageRow(string $path): array
{
    $row = DB::table('stats_daily_pages')->where('path', $path)->first();

    return array_map('intval', array_intersect_key((array) $row, array_flip(['pageviews', 'visitors', 'entries', 'exits'])));
}

function recCloseVisits(): int
{
    return app(Recorder::class)->closeIdleVisits(CarbonImmutable::now());
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'UTC'));
    $this->site = Site::create(['name' => 'Example', 'domains' => ['example.com']]);
});

it('continues a visit within the timeout and dedupes visitors', function () {
    recHit($this->site, 'pageview', 'https://example.com/a', ['r' => 'https://www.google.com/']);
    $this->travel(10)->minutes();
    recHit($this->site, 'pageview', 'https://example.com/b', ['r' => 'https://example.com/a']);
    $this->travel(10)->minutes();
    recHit($this->site, 'pageview', 'https://example.com/a', ['r' => 'https://example.com/b']);

    expect(recTotals())->toMatchArray(['pageviews' => 3, 'visitors' => 1, 'visits' => 1])
        ->and(recPageRow('/a'))->toBe(['pageviews' => 2, 'visitors' => 1, 'entries' => 1, 'exits' => 0])
        ->and(recPageRow('/b'))->toBe(['pageviews' => 1, 'visitors' => 1, 'entries' => 0, 'exits' => 0]);

    $sources = DB::table('stats_daily_sources')->get();
    expect($sources)->toHaveCount(1)
        ->and($sources[0]->source_type)->toBe('search')
        ->and($sources[0]->source)->toBe('google.com')
        ->and([(int) $sources[0]->visits, (int) $sources[0]->visitors, (int) $sources[0]->pageviews])->toBe([1, 1, 3]);

    $visit = DB::table('stats_visits')->first();
    expect((int) $visit->pageviews)->toBe(3)
        ->and($visit->last_seen_at)->toBe('2026-11-11 10:20:00')
        ->and($visit->exit_path_hash)->toBe(hash('sha256', '/a'));
});

it('starts a new visit after 30 minutes of inactivity', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    $this->travel(29)->minutes();
    recHit($this->site, 'pageview', 'https://example.com/a');
    $this->travel(31)->minutes();
    recHit($this->site, 'pageview', 'https://example.com/a', ['r' => 'https://chatgpt.com/']);

    expect(recTotals())->toMatchArray(['pageviews' => 3, 'visitors' => 1, 'visits' => 2])
        ->and(recPageRow('/a'))->toMatchArray(['entries' => 2, 'visitors' => 1])
        ->and(DB::table('stats_daily_sources')->pluck('visits', 'source_type')->map(fn ($v) => (int) $v)->all())->toBe(['direct' => 1, 'ai' => 1]);

    $dims = DB::table('stats_daily_dimensions')->get();
    expect($dims)->toHaveCount(5)
        ->and($dims->every(fn ($d) => (int) $d->visits === 2 && (int) $d->visitors === 1))->toBeTrue()
        ->and($dims->pluck('value', 'dimension')->all())->toMatchArray(['device' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS']);
});

it('counts different visitors separately', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    recHit($this->site, 'pageview', 'https://example.com/a', [], '198.51.100.8');

    expect(recTotals())->toMatchArray(['pageviews' => 2, 'visitors' => 2, 'visits' => 2])
        ->and(recPageRow('/a'))->toMatchArray(['visitors' => 2]);
});

it('records outbound, download and custom events with deduped visitors', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    recHit($this->site, 'outbound', 'https://example.com/a', ['g' => 'https://other.org/x?ref=1#y']);
    recHit($this->site, 'outbound', 'https://example.com/a?b=2', ['g' => 'https://other.org/x?ref=2']);
    recHit($this->site, 'download', 'https://example.com/a', ['g' => 'https://example.com/app.apk']);
    recHit($this->site, 'event', 'https://example.com/a', ['n' => 'signup', 'l' => 'Hero']);
    recHit($this->site, 'event', 'https://example.com/a', ['n' => 'signup', 'l' => 'Hero'], '198.51.100.8');

    $events = DB::table('stats_daily_events')->orderBy('kind')->get()->keyBy('kind');
    expect($events)->toHaveCount(3)
        ->and($events['outbound']->target)->toBe('https://other.org/x')
        ->and($events['outbound']->name)->toBe('')
        ->and($events['outbound']->path)->toBe('/a')
        ->and([(int) $events['outbound']->count, (int) $events['outbound']->visitors])->toBe([2, 1])
        ->and($events['download']->target)->toBe('https://example.com/app.apk')
        ->and($events['custom']->name)->toBe('signup')
        ->and($events['custom']->label)->toBe('Hero')
        ->and($events['custom']->target)->toBeNull()
        ->and([(int) $events['custom']->count, (int) $events['custom']->visitors])->toBe([2, 2])
        ->and(recTotals())->toMatchArray(['events' => 5, 'outbound' => 2, 'visits' => 1, 'pageviews' => 1]);
});

it('keeps event labels and targets apart', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    recHit($this->site, 'event', 'https://example.com/a', ['n' => 'cta', 'l' => 'pro', 'g' => 'https://example.com/checkout?plan=pro']);
    recHit($this->site, 'event', 'https://example.com/a', ['n' => 'cta', 'l' => 'basic', 'g' => 'https://example.com/checkout']);
    recHit($this->site, 'outbound', 'https://example.com/a', ['g' => 'https://github.com/x/pairdock', 'l' => 'pairdock']);

    $rows = DB::table('stats_daily_events')->orderBy('kind')->orderBy('label')->get(['kind', 'name', 'label', 'target']);
    expect($rows->map(fn ($r) => (array) $r)->all())->toBe([
        ['kind' => 'custom', 'name' => 'cta', 'label' => 'basic', 'target' => 'https://example.com/checkout'],
        ['kind' => 'custom', 'name' => 'cta', 'label' => 'pro', 'target' => 'https://example.com/checkout'],
        ['kind' => 'outbound', 'name' => '', 'label' => 'pairdock', 'target' => 'https://github.com/x/pairdock'],
    ]);
});

it('credits events to the source and entry page of the visit', function () {
    recHit($this->site, 'pageview', 'https://example.com/landing?utm_source=news&utm_campaign=launch');
    recHit($this->site, 'pageview', 'https://example.com/pricing');
    recHit($this->site, 'outbound', 'https://example.com/pricing', ['g' => 'https://other.org/']);
    recHit($this->site, 'download', 'https://example.com/pricing', ['g' => 'https://example.com/app.apk']);
    recHit($this->site, 'event', 'https://example.com/pricing', ['n' => 'signup']);

    $source = DB::table('stats_daily_sources')->first();
    $landing = DB::table('stats_daily_pages')->where('path', '/landing')->first();
    $pricing = DB::table('stats_daily_pages')->where('path', '/pricing')->first();
    expect([(int) $source->outbound, (int) $source->events])->toBe([1, 2])
        ->and([(int) $landing->entry_outbound, (int) $landing->entry_events])->toBe([1, 2])
        ->and([(int) $pricing->entry_outbound, (int) $pricing->entry_events])->toBe([0, 0]);

    recCloseVisits();
    expect(recCloseVisits())->toBe(0);
    $this->travel(31)->minutes();
    recCloseVisits();
    expect((int) DB::table('stats_daily_pages')->where('path', '/landing')->value('entry_pageviews'))->toBe(2);
});

it('records entry pages by source and page-to-page moves', function () {
    recHit($this->site, 'pageview', 'https://example.com/a', ['r' => 'https://www.google.com/']);
    recHit($this->site, 'pageview', 'https://example.com/b');
    recHit($this->site, 'pageview', 'https://example.com/b');
    recHit($this->site, 'pageview', 'https://example.com/c');
    recHit($this->site, 'pageview', 'https://example.com/a', [], '198.51.100.8');
    recHit($this->site, 'pageview', 'https://example.com/b', [], '198.51.100.8');

    $entries = DB::table('stats_daily_entries')->orderBy('source_type')->get();
    expect($entries->map(fn ($r) => [$r->path, $r->source_type, $r->source, (int) $r->visits, (int) $r->visitors])->all())->toBe([
        ['/a', 'direct', null, 1, 1],
        ['/a', 'search', 'google.com', 1, 1],
    ]);

    $moves = DB::table('stats_daily_transitions')->orderBy('from_path')->orderBy('to_path')->get();
    expect($moves->map(fn ($r) => [$r->from_path, $r->to_path, (int) $r->count, (int) $r->visitors])->all())->toBe([
        ['/a', '/b', 2, 2],
        ['/b', '/c', 1, 1],
    ]);
});

it('stores page titles, types and content groups, and the site language of each page', function () {
    $this->site->update(['locales' => ['en', 'zh-cn'], 'default_locale' => 'en']);
    recHit($this->site, 'pageview', 'https://example.com/blog/x', ['pt' => 'Old title', 'py' => 'article', 'pg' => 'post-1']);
    recHit($this->site, 'pageview', 'https://example.com/blog/x', ['pt' => 'New title', 'py' => 'article', 'pg' => 'post-1']);
    recHit($this->site, 'pageview', 'https://example.com/zh-cn/blog/x', ['pt' => '标题', 'py' => 'article', 'pg' => 'post-1']);
    recHit($this->site, 'pageview', 'https://example.com/plain');

    $pages = DB::table('stats_daily_pages')->orderBy('path')->get()->keyBy('path');
    expect([$pages['/blog/x']->title, $pages['/blog/x']->page_type, $pages['/blog/x']->content_group])->toBe(['New title', 'article', 'post-1'])
        ->and([$pages['/zh-cn/blog/x']->title, $pages['/zh-cn/blog/x']->content_group])->toBe(['标题', 'post-1'])
        ->and([$pages['/plain']->title, $pages['/plain']->page_type])->toBe([null, null]);

    $locales = DB::table('stats_daily_dimensions')->where('dimension', 'locale')->orderBy('value')->get()->keyBy('value');
    expect([(int) $locales['en']->visits, (int) $locales['en']->pageviews, (int) $locales['en']->visitors])->toBe([1, 3, 1])
        ->and([(int) $locales['zh-cn']->visits, (int) $locales['zh-cn']->pageviews, (int) $locales['zh-cn']->visitors])->toBe([0, 1, 1]);
});

it('records no locale dimension for sites without locales', function () {
    recHit($this->site, 'pageview', 'https://example.com/en/a');

    expect(DB::table('stats_daily_dimensions')->where('dimension', 'locale')->count())->toBe(0);
});

it('adds the pageviews of a closed visit to its dimensions', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    recHit($this->site, 'pageview', 'https://example.com/b');
    recHit($this->site, 'pageview', 'https://example.com/c');

    expect((int) DB::table('stats_daily_dimensions')->where('dimension', 'device')->value('pageviews'))->toBe(0);
    $this->travel(31)->minutes();
    recCloseVisits();

    $dims = DB::table('stats_daily_dimensions')->get()->keyBy('dimension');
    expect((int) $dims['device']->pageviews)->toBe(3)
        ->and((int) $dims['browser']->pageviews)->toBe(3)
        ->and((int) $dims['country']->pageviews)->toBe(3);
});

it('drops pages and events on excluded paths', function () {
    $this->site->update(['exclude_paths' => ['/admin/*', '/preview']]);
    recHit($this->site, 'pageview', 'https://example.com/admin/users');
    recHit($this->site, 'pageview', 'https://example.com/preview');
    recHit($this->site, 'event', 'https://example.com/admin/x', ['n' => 'click']);
    recHit($this->site, 'pageview', 'https://example.com/previews');

    expect(DB::table('stats_daily_pages')->pluck('path')->all())->toBe(['/previews'])
        ->and(DB::table('stats_daily_events')->count())->toBe(0);
});

it('keeps a visit open while events arrive', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    $this->travel(20)->minutes();
    recHit($this->site, 'event', 'https://example.com/a', ['n' => 'scroll']);
    $this->travel(25)->minutes();
    recHit($this->site, 'pageview', 'https://example.com/b');

    expect(recTotals())->toMatchArray(['visits' => 1, 'pageviews' => 2]);
});

it('closes a bounced visit', function () {
    recHit($this->site, 'pageview', 'https://example.com/a', ['r' => 'https://news.ycombinator.com/']);
    $this->travel(30)->minutes();
    expect(recCloseVisits())->toBe(0);

    $this->travel(1)->minutes();
    expect(recCloseVisits())->toBe(1)
        ->and(DB::table('stats_visits')->count())->toBe(0)
        ->and(recTotals())->toMatchArray(['bounces' => 1, 'duration_seconds' => 0])
        ->and(recPageRow('/a'))->toMatchArray(['exits' => 1])
        ->and((int) DB::table('stats_daily_sources')->value('bounces'))->toBe(1);
});

it('closes a longer visit with its duration and exit page', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    $this->travel(5)->minutes();
    recHit($this->site, 'pageview', 'https://example.com/b');
    $this->travel(31)->minutes();

    expect(recCloseVisits())->toBe(1)
        ->and(recTotals())->toMatchArray(['bounces' => 0, 'duration_seconds' => 300])
        ->and(recPageRow('/a'))->toMatchArray(['entries' => 1, 'exits' => 0])
        ->and(recPageRow('/b'))->toMatchArray(['entries' => 0, 'exits' => 1])
        ->and((int) DB::table('stats_daily_sources')->value('bounces'))->toBe(0);
});

it('uses the site timezone for dates and starts a new visit at local midnight', function () {
    $this->site->update(['timezone' => 'Asia/Tokyo']);
    $this->travelTo(CarbonImmutable::parse('2026-11-11 14:50:00', 'UTC')); // 23:50 in Tokyo
    recHit($this->site, 'pageview', 'https://example.com/a', ['r' => 'https://www.bing.com/']);
    $this->travel(20)->minutes(); // 00:10 on 11-12 in Tokyo: new daily salt, so a new visitor and visit
    recHit($this->site, 'pageview', 'https://example.com/b');

    $days = DB::table('stats_daily_totals')->orderBy('date')->get();
    expect($days->pluck('date')->all())->toBe(['2026-11-11', '2026-11-12'])
        ->and($days->map(fn ($d) => [(int) $d->visits, (int) $d->pageviews, (int) $d->visitors])->all())->toBe([[1, 1, 1], [1, 1, 1]])
        ->and(DB::table('stats_daily_sources')->orderBy('date')->pluck('source_type')->all())->toBe(['search', 'direct']);

    $this->travel(31)->minutes();
    expect(recCloseVisits())->toBe(2)
        ->and((int) DB::table('stats_daily_pages')->where('date', '2026-11-11')->where('path', '/a')->value('exits'))->toBe(1)
        ->and((int) DB::table('stats_daily_pages')->where('date', '2026-11-12')->where('path', '/b')->value('exits'))->toBe(1)
        ->and(DB::table('stats_daily_totals')->pluck('bounces')->map(fn ($v) => (int) $v)->all())->toBe([1, 1]);
});

it('removes the uniques row when the counter write fails', function () {
    recHit($this->site, 'pageview', 'https://example.com/a');
    $before = DB::table('stats_uniques')->count();

    Schema::drop('stats_daily_pages');
    Log::shouldReceive('warning')->once();
    recHit($this->site, 'pageview', 'https://example.com/b');

    expect(DB::table('stats_uniques')->count())->toBe($before);
});

it('records ai fetches without visitors', function () {
    app(Recorder::class)->recordEvent($this->site, null, CarbonImmutable::now(), 'ai_fetch', 'gptbot', null, null, '/docs');
    app(Recorder::class)->recordEvent($this->site, null, CarbonImmutable::now(), 'ai_fetch', 'gptbot', null, null, '/docs');

    $event = DB::table('stats_daily_events')->first();
    expect([$event->kind, $event->name, $event->path, $event->target])->toBe(['ai_fetch', 'gptbot', '/docs', null])
        ->and([(int) $event->count, (int) $event->visitors])->toBe([2, 0])
        ->and(DB::table('stats_uniques')->count())->toBe(0)
        ->and(DB::table('stats_daily_totals')->count())->toBe(0);
});
