<?php

use N2ns\Stats\Collector\Payload;

const PAYLOAD_SITE = '01JCZ0000000000000000000AB';

function payloadJson(array $overrides = []): string
{
    return json_encode(array_filter(array_merge(['s' => PAYLOAD_SITE, 't' => 'pageview', 'u' => 'https://example.com/a?x=1'], $overrides), fn ($v) => $v !== null));
}

it('parses a pageview', function () {
    $p = Payload::fromJson(payloadJson(['s' => strtolower(PAYLOAD_SITE), 'r' => 'https://google.com/', 'w' => 1280]));
    expect($p)->toEqual(new Payload(PAYLOAD_SITE, 'pageview', 'https://example.com/a?x=1', 'https://google.com/', null, null, 1280));
});

it('parses page details', function () {
    $p = Payload::fromJson(payloadJson(['pt' => 'Pricing – Example', 'py' => 'product', 'pg' => 'plan-pro']));
    expect($p?->title)->toBe('Pricing – Example')
        ->and($p?->pageType)->toBe('product')
        ->and($p?->group)->toBe('plan-pro');
});

it('parses events, outbound and download', function () {
    expect(Payload::fromJson(payloadJson(['t' => 'event', 'n' => 'signup.click', 'l' => 'Hero button', 'g' => 'https://example.com/checkout'])))
        ->toEqual(new Payload(PAYLOAD_SITE, 'event', 'https://example.com/a?x=1', null, 'signup.click', 'Hero button', null, 'https://example.com/checkout'))
        ->and(Payload::fromJson(payloadJson(['t' => 'event', 'n' => 'x']))?->target)->toBeNull()
        ->and(Payload::fromJson(payloadJson(['t' => 'outbound', 'g' => 'https://other.org/x', 'l' => 'pairdock', 'r' => 'https://ignored.com/'])))
        ->toEqual(new Payload(PAYLOAD_SITE, 'outbound', 'https://example.com/a?x=1', null, null, 'pairdock', null, 'https://other.org/x'))
        ->and(Payload::fromJson(payloadJson(['t' => 'download', 'g' => 'https://example.com/app.apk']))?->target)->toBe('https://example.com/app.apk')
        ->and(Payload::fromJson(payloadJson(['t' => 'outbound', 'g' => 'https://other.org/x', 'pt' => 'ignored']))?->title)->toBeNull();
});

it('parses engagement reports', function () {
    $p = Payload::fromJson(payloadJson(['t' => 'engagement', 'e' => 75, 'sp' => 60]));
    expect([$p?->seconds, $p?->scroll, $p?->previousSeconds, $p?->previousScroll])->toBe([75, 60, 0, 0]);

    $p = Payload::fromJson(payloadJson(['t' => 'engagement', 'e' => 300, 'sp' => 100, 'e0' => 75, 'sp0' => 60]));
    expect([$p?->seconds, $p?->scroll, $p?->previousSeconds, $p?->previousScroll])->toBe([300, 100, 75, 60]);
});

it('parses the same fields from an array', function () {
    expect(Payload::fromArray(['s' => PAYLOAD_SITE, 't' => 'pageview', 'u' => 'https://example.com/', 'py' => 'home'])?->pageType)->toBe('home')
        ->and(Payload::fromArray(['s' => PAYLOAD_SITE, 't' => 'pageview']))->toBeNull();
});

it('rejects malformed payloads', function (string $body) {
    expect(Payload::fromJson($body))->toBeNull();
})->with([
    'empty' => [''],
    'not json' => ['{'],
    'list' => ['[1,2]'],
    'too large' => [payloadJson(['r' => 'https://a.com/'.str_repeat('a', 4100)])],
    'bad site' => [payloadJson(['s' => 'abc'])],
    'site with I' => [payloadJson(['s' => '01JCZ000000000000000000IAB'])],
    'bad type' => [payloadJson(['t' => 'click'])],
    'missing url' => [json_encode(['s' => PAYLOAD_SITE, 't' => 'pageview'])],
    'relative url' => [payloadJson(['u' => '/a'])],
    'javascript url' => [payloadJson(['u' => 'javascript:alert(1)'])],
    'long url' => [payloadJson(['u' => 'https://example.com/'.str_repeat('a', 2040)])],
    'long referrer' => [payloadJson(['r' => 'https://a.com/'.str_repeat('a', 2040)])],
    'event without name' => [payloadJson(['t' => 'event'])],
    'event bad name' => [payloadJson(['t' => 'event', 'n' => 'Sign Up'])],
    'event long label' => [payloadJson(['t' => 'event', 'n' => 'x', 'l' => str_repeat('a', 121)])],
    'outbound without url' => [payloadJson(['t' => 'outbound'])],
    'outbound url in label only' => [payloadJson(['t' => 'outbound', 'l' => 'https://other.org/x'])],
    'outbound bad url' => [payloadJson(['t' => 'outbound', 'g' => 'mailto:a@b.c'])],
    'event bad target' => [payloadJson(['t' => 'event', 'n' => 'x', 'g' => '/checkout'])],
    'outbound long label' => [payloadJson(['t' => 'outbound', 'g' => 'https://o.org/', 'l' => str_repeat('a', 121)])],
    'label with control character' => [payloadJson(['t' => 'event', 'n' => 'x', 'l' => "a\nb"])],
    'long title' => [payloadJson(['pt' => str_repeat('a', 201)])],
    'bad page type' => [payloadJson(['py' => 'Product Page'])],
    'long group' => [payloadJson(['pg' => str_repeat('a', 121)])],
    'title not a string' => [payloadJson(['pt' => 5])],
    'engagement without seconds' => [payloadJson(['t' => 'engagement', 'sp' => 10])],
    'engagement without scroll' => [payloadJson(['t' => 'engagement', 'e' => 10])],
    'engagement seconds string' => [payloadJson(['t' => 'engagement', 'e' => '10', 'sp' => 10])],
    'engagement too long' => [payloadJson(['t' => 'engagement', 'e' => 86401, 'sp' => 10])],
    'engagement scroll over 100' => [payloadJson(['t' => 'engagement', 'e' => 10, 'sp' => 101])],
    'engagement negative' => [payloadJson(['t' => 'engagement', 'e' => -1, 'sp' => 10])],
    'engagement shrinking seconds' => [payloadJson(['t' => 'engagement', 'e' => 10, 'sp' => 50, 'e0' => 20, 'sp0' => 50])],
    'engagement shrinking scroll' => [payloadJson(['t' => 'engagement', 'e' => 30, 'sp' => 40, 'e0' => 20, 'sp0' => 50])],
    'width string' => [payloadJson(['w' => '1280'])],
    'width too big' => [payloadJson(['w' => 10001])],
    'width negative' => [payloadJson(['w' => -1])],
]);
