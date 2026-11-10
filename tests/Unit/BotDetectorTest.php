<?php

use N2ns\Stats\Support\BotDetector;

it('treats empty and listed user agents as bots', function (?string $ua, bool $bot) {
    expect((new BotDetector)->isBot($ua))->toBe($bot);
})->with([
    [null, true],
    ['', true],
    ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
    ['curl/8.4.0', true],
    ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', true],
    ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0 Safari/537.36', true],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', false],
]);

it('names the ai fetcher', function () {
    $d = new BotDetector;
    expect($d->aiFetcher('Mozilla/5.0 (compatible; ChatGPT-User/1.0; +https://openai.com/bot)'))->toBe('chatgpt-user')
        ->and($d->aiFetcher('Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'))->toBe('claudebot')
        ->and($d->aiFetcher('Mozilla/5.0 (compatible; Googlebot/2.1)'))->toBeNull()
        ->and($d->aiFetcher(null))->toBeNull();
});
