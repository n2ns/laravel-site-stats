<?php

namespace N2ns\Stats\Http\View;

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
}
