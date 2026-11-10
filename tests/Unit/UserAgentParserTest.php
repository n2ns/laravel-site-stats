<?php

use N2ns\Stats\Support\UserAgentParser;

it('parses browser, os and device', function (string $ua, ?int $width, string $device, string $browser, string $os) {
    expect((new UserAgentParser)->parse($ua, $width))->toBe(['device' => $device, 'browser' => $browser, 'os' => $os]);
})->with([
    'chrome windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 1920, 'desktop', 'Chrome', 'Windows'],
    'edge windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0', null, 'desktop', 'Edge', 'Windows'],
    'opera' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 OPR/115.0.0.0', null, 'desktop', 'Opera', 'Windows'],
    'safari mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15', 1440, 'desktop', 'Safari', 'macOS'],
    'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0', null, 'desktop', 'Firefox', 'Linux'],
    'chromeos' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', null, 'desktop', 'Chrome', 'ChromeOS'],
    'iphone safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 390, 'mobile', 'Safari', 'iOS'],
    'iphone chrome' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0.0.0 Mobile/15E148 Safari/604.1', 390, 'mobile', 'Chrome', 'iOS'],
    'iphone firefox' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/132.0 Mobile/15E148 Safari/605.1.15', null, 'mobile', 'Firefox', 'iOS'],
    'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 820, 'tablet', 'Safari', 'iOS'],
    'android phone' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36', 412, 'mobile', 'Chrome', 'Android'],
    'android wide phone ua' => ['Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36', 800, 'tablet', 'Chrome', 'Android'],
    'android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', null, 'tablet', 'Chrome', 'Android'],
    'samsung internet' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36', 384, 'mobile', 'Samsung Internet', 'Android'],
    'edge android' => ['Mozilla/5.0 (Linux; Android 14; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 EdgA/130.0.0.0', null, 'mobile', 'Edge', 'Android'],
    'unknown' => ['SomeClient/1.0', null, 'desktop', 'Other', 'Other'],
]);

it('returns unknown for an empty user agent', function () {
    expect((new UserAgentParser)->parse(null))->toBe(['device' => 'unknown', 'browser' => 'Other', 'os' => 'Other']);
});
