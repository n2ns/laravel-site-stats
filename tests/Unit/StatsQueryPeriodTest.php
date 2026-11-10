<?php

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
