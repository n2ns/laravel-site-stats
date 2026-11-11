<?php

namespace N2ns\Stats\Http\View;

use Carbon\CarbonImmutable;

/** Number formatting for the dashboard views. */
final class Format
{
    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals);
    }

    /** 0.4231 => "42.3%" */
    public static function percent(int|float $ratio): string
    {
        return number_format($ratio * 100, 1).'%';
    }

    /** 125 => "2m 05s" */
    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return sprintf('%dm %02ds', intdiv($seconds, 60), $seconds % 60);
        }

        return sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /** Relative change from $previous to $current, or null when there is nothing to compare with. */
    public static function change(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return null;
        }

        return ($current - $previous) / $previous;
    }

    /** +12.5% / −3.0% / "–" */
    public static function signedPercent(?float $change): string
    {
        if ($change === null) {
            return '–';
        }

        return ($change > 0 ? '+' : ($change < 0 ? '−' : '±')).number_format(abs($change) * 100, 1).'%';
    }

    /** Short axis number: 950 => "950", 1200 => "1.2k", 3400000 => "3.4M" */
    public static function compact(int $value): string
    {
        foreach ([1_000_000_000 => 'B', 1_000_000 => 'M', 1_000 => 'k'] as $unit => $suffix) {
            if ($value >= $unit) {
                $short = round($value / $unit, $value >= 10 * $unit ? 0 : 1);

                return rtrim(rtrim(number_format($short, 1, '.', ''), '0'), '.').$suffix;
            }
        }

        return (string) $value;
    }

    /** A Y-m-d date in one of the dashboard's date formats ('day', 'weekday', ...), in the app locale. */
    public static function date(string $date, string $format = 'day'): string
    {
        return CarbonImmutable::parse($date)->locale(app()->getLocale())->isoFormat((string) __('stats::dashboard.date_formats.'.$format));
    }
}
