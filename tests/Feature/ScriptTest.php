<?php

$script = fn (): string => (string) file_get_contents(__DIR__.'/../../resources/js/stats.js');

it('serves the tracking script with long cache headers and an etag', function () use ($script) {
    $response = $this->get('/js/stats.js');

    $response->assertOk()
        ->assertHeader('ETag', '"'.sha1($script()).'"');
    expect($response->headers->get('Content-Type'))->toStartWith('application/javascript')
        ->and($response->headers->getCacheControlDirective('public'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('max-age'))->toBe('86400')
        ->and($response->getContent())->toBe($script());
});

it('answers 304 without a body when the etag matches', function () use ($script) {
    $response = $this->get('/js/stats.js', ['If-None-Match' => '"'.sha1($script()).'"']);

    $response->assertStatus(304)->assertHeader('ETag', '"'.sha1($script()).'"');
    expect($response->getContent())->toBe('');
});

it('serves the full script when the etag does not match', function () {
    $this->get('/js/stats.js', ['If-None-Match' => '"stale"'])
        ->assertOk()
        ->assertSee('n2nStats', false);
});

it('does not set cookies', function () {
    expect($this->get('/js/stats.js')->headers->getCookies())->toBe([]);
});
