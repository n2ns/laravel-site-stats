<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use N2ns\Stats\Reports\Period;

function span(Period $p): string
{
    return $p->from->toDateString().'..'.$p->to->toDateString();
}

it('resolves presets in the site timezone', function () {
    Carbon::setTestNow('2026-03-01 02:00:00'); // UTC; still 28 Feb in New York

    expect(span(Period::preset('today', 'UTC')))->toBe('2026-03-01..2026-03-01')
        ->and(span(Period::preset('today', 'America/New_York')))->toBe('2026-02-28..2026-02-28')
        ->and(span(Period::preset('yesterday', 'UTC')))->toBe('2026-02-28..2026-02-28')
        ->and(span(Period::preset('7d', 'UTC')))->toBe('2026-02-23..2026-03-01')
        ->and(span(Period::preset('15d', 'UTC')))->toBe('2026-02-15..2026-03-01')
        ->and(span(Period::preset('30d', 'UTC')))->toBe('2026-01-31..2026-03-01')
        ->and(Period::preset('90d', 'UTC')->days())->toBe(90)
        ->and(span(Period::preset('month', 'America/New_York')))->toBe('2026-02-01..2026-02-28')
        ->and(span(Period::preset('last_month', 'UTC')))->toBe('2026-02-01..2026-02-28');
});

it('computes the previous period of equal length', function () {
    expect(span(Period::parse('2026-03-01', '2026-03-31')->previous()))->toBe('2026-01-29..2026-02-28')
        ->and(span(Period::parse('2026-03-01', '2026-03-01')->previous()))->toBe('2026-02-28..2026-02-28');
});

it('rejects invalid custom ranges', function (string $from, string $to) {
    Period::parse($from, $to);
})->with([
    ['2026-02-30', '2026-03-01'],
    ['2026-3-1', '2026-03-02'],
    ['yesterday', '2026-03-02'],
    ['2026-03-02', '2026-03-01'],
    ['2025-01-01', '2026-01-02'],
])->throws(InvalidArgumentException::class);

it('accepts a range of exactly 366 days', function () {
    expect(Period::parse('2024-01-01', '2024-12-31')->days())->toBe(366);
});

it('compares complete days only', function (string $from, string $to, string $current, string $previous) {
    [$c, $p] = Period::parse($from, $to)->comparison(CarbonImmutable::parse('2026-03-10'));

    expect(span($c))->toBe($current)->and(span($p))->toBe($previous);
})->with([
    'past range' => ['2026-03-01', '2026-03-07', '2026-03-01..2026-03-07', '2026-02-22..2026-02-28'],
    'ending today' => ['2026-03-04', '2026-03-10', '2026-03-04..2026-03-09', '2026-02-26..2026-03-03'],
    'today only' => ['2026-03-10', '2026-03-10', '2026-03-09..2026-03-09', '2026-03-08..2026-03-08'],
    'month so far' => ['2026-03-01', '2026-03-10', '2026-03-01..2026-03-09', '2026-02-20..2026-02-28'],
]);

it('knows whether a period is still in progress', function () {
    $today = CarbonImmutable::parse('2026-03-10');

    expect(Period::parse('2026-03-01', '2026-03-10')->isPartial($today))->toBeTrue()
        ->and(Period::parse('2026-03-01', '2026-03-09')->isPartial($today))->toBeFalse();
});
