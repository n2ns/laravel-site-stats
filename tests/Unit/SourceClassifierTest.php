<?php

use N2ns\Stats\Support\Source;
use N2ns\Stats\Support\SourceClassifier;

function classifySource(string $url, ?string $referrer = null): Source
{
    return (new SourceClassifier)->classify($url, $referrer, fn (string $host): bool => in_array($host, ['example.com', 'www.example.com'], true));
}

it('classifies utm parameters first, cleaned and truncated', function () {
    $s = classifySource('https://example.com/?utm_source=News%0Aletter&utm_medium=email&utm_campaign='.str_repeat('c', 200).'&utm_content=a&utm_term=b&gclid=x', 'https://www.google.com/');
    expect($s->type)->toBe('utm')
        ->and($s->source)->toBe('Newsletter')
        ->and($s->medium)->toBe('email')
        ->and($s->campaign)->toHaveLength(128)
        ->and($s->content)->toBe('a')
        ->and($s->term)->toBe('b');
});

it('ignores an empty utm_source', function () {
    expect(classifySource('https://example.com/?utm_source=%20', null)->type)->toBe('direct');
});

it('uses click ids without a referrer, otherwise direct', function () {
    expect(classifySource('https://example.com/?fbclid=abc'))->toEqual(new Source('social', 'meta.com'))
        ->and(classifySource('https://example.com/?gclid=abc', ''))->toEqual(new Source('search', 'google.com'))
        ->and(classifySource('https://example.com/')->type)->toBe('direct')
        ->and(classifySource('https://example.com/', 'android-app://com.google.android.gm/')->type)->toBe('direct');
});

it('counts an own-site referrer as direct', function () {
    expect(classifySource('https://example.com/b', 'https://www.example.com/a')->type)->toBe('direct');
});

it('classifies search, ai, social and referral referrers', function (string $referrer, string $type, string $source) {
    $s = classifySource('https://example.com/', $referrer);
    expect([$s->type, $s->source])->toBe([$type, $source]);
})->with([
    ['https://www.google.com/', 'search', 'google.com'],
    ['https://www.google.co.uk/search', 'search', 'google.co.uk'],
    ['https://duckduckgo.com/', 'search', 'duckduckgo.com'],
    ['https://chatgpt.com/', 'ai', 'chatgpt.com'],
    ['https://gemini.google.com/app', 'ai', 'gemini.google.com'],
    ['https://www.perplexity.ai/', 'ai', 'perplexity.ai'],
    ['https://t.co/abc', 'social', 't.co'],
    ['https://m.facebook.com/', 'social', 'm.facebook.com'],
    ['https://www.microsoft.com/', 'referral', 'microsoft.com'],
    ['https://netflix.com/', 'referral', 'netflix.com'],
    ['https://news.ycombinator.com/item', 'referral', 'news.ycombinator.com'],
]);

it('uses a click id when the referrer is not search, ai or social', function () {
    expect(classifySource('https://example.com/?msclkid=1', 'https://blog.example.org/')->source)->toBe('bing.com');
});

it('produces a stable source key', function () {
    expect(classifySource('https://example.com/', 'https://chatgpt.com/')->key())
        ->toBe(hash('sha256', 'ai|chatgpt.com||||'));
});
