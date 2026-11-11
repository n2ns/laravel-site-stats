<?php

use Illuminate\Support\Facades\DB;
use N2ns\Stats\Support\Source;
use N2ns\Stats\Tests\StatsSeeder as S;

function aiUtmSource(int $siteId, string $date, Source $source, array $counts): void
{
    DB::table('stats_daily_sources')->insert([
        'site_id' => $siteId, 'date' => $date, 'source_key' => $source->key(), 'source_type' => $source->type, 'source' => $source->source,
        'utm_medium' => $source->medium, 'utm_campaign' => $source->campaign, 'utm_content' => $source->content, 'utm_term' => $source->term,
    ] + $counts);
}

function aiUtmEntry(int $siteId, string $date, string $path, Source $source, int $visits, int $visitors): void
{
    DB::table('stats_daily_entries')->insert([
        'site_id' => $siteId, 'date' => $date, 'path' => $path, 'path_hash' => hash('sha256', $path), 'source_key' => $source->key(),
        'source_type' => $source->type, 'source' => $source->source, 'utm_campaign' => $source->campaign, 'visits' => $visits, 'visitors' => $visitors,
    ]);
}

it('moves sources that AI assistants tagged with utm_source only to AI referrals', function () {
    $site = S::site();
    $chatgpt = new Source('utm', 'chatgpt.com');
    $ads = new Source('utm', 'chatgpt.com', 'cpc'); // a campaign of the site's own
    $news = new Source('utm', 'newsletter');
    $ai = new Source('ai', 'chatgpt.com');

    aiUtmSource($site->id, '2026-03-01', $chatgpt, ['visits' => 0, 'visitors' => 6, 'pageviews' => 8]);
    aiUtmSource($site->id, '2026-03-01', $ads, ['visits' => 1, 'visitors' => 1, 'pageviews' => 1]);
    aiUtmSource($site->id, '2026-03-01', $news, ['visits' => 2, 'visitors' => 2, 'pageviews' => 2]);
    aiUtmSource($site->id, '2026-03-02', $chatgpt, ['visits' => 2, 'visitors' => 2, 'pageviews' => 3, 'bounces' => 1]);
    aiUtmSource($site->id, '2026-03-02', $ai, ['visits' => 5, 'visitors' => 4, 'pageviews' => 9, 'bounces' => 2]);
    aiUtmEntry($site->id, '2026-03-02', '/a', $chatgpt, 2, 2);
    aiUtmEntry($site->id, '2026-03-02', '/a', $ai, 1, 1);
    aiUtmEntry($site->id, '2026-03-02', '/b', $chatgpt, 1, 1);
    aiUtmEntry($site->id, '2026-03-02', '/b', $ads, 1, 1);
    S::visit($site, 'v1', new DateTimeImmutable('2026-03-02 10:00:00'));
    DB::table('stats_visits')->update(['source_key' => $chatgpt->key()]);
    $unique = fn (string $scope, string $visitor) => DB::table('stats_uniques')->insert(['site_id' => $site->id, 'date' => '2026-03-02', 'scope' => hash('sha256', $scope), 'visitor_hash' => $visitor]);
    $unique('source:'.$chatgpt->key(), 'v1');
    $unique('source:'.$chatgpt->key(), 'v2');
    $unique('source:'.$ai->key(), 'v2'); // already counted for the AI source as well
    $unique('entry:'.hash('sha256', '/a').':'.$chatgpt->key(), 'v1');

    (require __DIR__.'/../../database/migrations/2026_11_13_000001_count_ai_assistant_utm_sources_as_ai.php')->up();

    $sources = DB::table('stats_daily_sources')->orderBy('date')->orderBy('source_type')->orderBy('source')->get()
        ->map(fn ($r) => [$r->date, $r->source_type, $r->source, $r->utm_medium, $r->source_key === (new Source($r->source_type, $r->source, $r->utm_medium))->key(), (int) $r->visits, (int) $r->visitors, (int) $r->pageviews, (int) $r->bounces])->all();
    expect($sources)->toBe([
        ['2026-03-01', 'ai', 'chatgpt.com', null, true, 0, 6, 8, 0],
        ['2026-03-01', 'utm', 'chatgpt.com', 'cpc', true, 1, 1, 1, 0],
        ['2026-03-01', 'utm', 'newsletter', null, true, 2, 2, 2, 0],
        ['2026-03-02', 'ai', 'chatgpt.com', null, true, 7, 6, 12, 3],
    ]);

    $entries = DB::table('stats_daily_entries')->orderBy('path')->orderBy('source_type')->get()
        ->map(fn ($r) => [$r->path, $r->source_type, $r->source_key === $ai->key(), (int) $r->visits, (int) $r->visitors])->all();
    expect($entries)->toBe([
        ['/a', 'ai', true, 3, 3],
        ['/b', 'ai', true, 1, 1],
        ['/b', 'utm', false, 1, 1],
    ])->and(DB::table('stats_visits')->value('source_key'))->toBe($ai->key());

    $scopes = fn (string $scope) => DB::table('stats_uniques')->where('scope', hash('sha256', $scope))->orderBy('visitor_hash')->pluck('visitor_hash')->all();
    expect($scopes('source:'.$ai->key()))->toBe(['v1', 'v2'])
        ->and($scopes('source:'.$chatgpt->key()))->toBe([])
        ->and($scopes('entry:'.hash('sha256', '/a').':'.$ai->key()))->toBe(['v1'])
        ->and($scopes('entry:'.hash('sha256', '/a').':'.$chatgpt->key()))->toBe([]);
});
