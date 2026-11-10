<?php

use N2ns\Stats\Support\PathNormalizer;

it('normalizes paths', function (string $input, ?string $expected) {
    expect((new PathNormalizer)->normalize($input))->toBe($expected);
})->with([
    ['https://example.com/a/b?x=1#top', '/a/b'],
    ['https://example.com', '/'],
    ['https://example.com/?utm_source=x', '/'],
    ['/pricing?plan=pro', '/pricing'],
    ['/docs#intro', '/docs'],
    ['/%E4%B8%AD%E6%96%87', '/%E4%B8%AD%E6%96%87'],
    ['relative/path', null],
    ['//evil.com/x', null],
    ["/a\x01b", null],
    ['', null],
]);

it('rejects paths over 512 characters', function () {
    $n = new PathNormalizer;
    expect($n->normalize('/'.str_repeat('a', 511)))->toHaveLength(512)
        ->and($n->normalize('/'.str_repeat('a', 512)))->toBeNull();
});

it('cleans external urls', function (string $input, ?string $expected) {
    expect((new PathNormalizer)->cleanUrl($input))->toBe($expected);
})->with([
    ['https://user:pass@Example.org:8443/file.pdf?token=1#x', 'https://example.org:8443/file.pdf'],
    ['http://example.org', 'http://example.org'],
    ['javascript:alert(1)', null],
    ['ftp://example.org/a', null],
    ['not a url', null],
]);
