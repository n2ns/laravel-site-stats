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

it('parses events, outbound and download', function () {
    expect(Payload::fromJson(payloadJson(['t' => 'event', 'n' => 'signup.click', 'l' => 'Hero button'])))
        ->toEqual(new Payload(PAYLOAD_SITE, 'event', 'https://example.com/a?x=1', null, 'signup.click', 'Hero button'))
        ->and(Payload::fromJson(payloadJson(['t' => 'outbound', 'l' => 'https://other.org/x', 'r' => 'https://ignored.com/']))?->referrer)->toBeNull()
        ->and(Payload::fromJson(payloadJson(['t' => 'download', 'l' => 'https://example.com/app.apk']))?->label)->toBe('https://example.com/app.apk');
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
    'outbound bad url' => [payloadJson(['t' => 'outbound', 'l' => 'mailto:a@b.c'])],
    'width string' => [payloadJson(['w' => '1280'])],
    'width too big' => [payloadJson(['w' => 10001])],
    'width negative' => [payloadJson(['w' => -1])],
]);
