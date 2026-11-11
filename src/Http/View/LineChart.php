<?php

namespace N2ns\Stats\Http\View;

/**
 * Geometry for the dashboard's inline SVG charts.
 *
 * The plot is drawn in a WIDTH x HEIGHT viewBox that the page stretches to its box
 * (preserveAspectRatio="none"); axis labels are HTML positioned in percent, so they stay
 * readable at any width. Each metric gets its own scale, as the chart shows one metric at a time.
 */
final class LineChart
{
    public const WIDTH = 800;

    public const HEIGHT = 240;

    /** Number of intervals on the y axis. */
    public const TICKS = 4;

    /** At most this many date labels on the x axis. */
    private const MAX_X_LABELS = 7;

    /**
     * @param  list<array<string, int|string>>  $series  one row per day: 'date' and an int per metric
     * @param  list<string>  $metrics
     * @param  list<array<string, int|string>>|null  $previous  the same number of days just before, drawn as a dashed line
     * @return array{
     *     layers: array<string, array{line: string, area: string, previous: string|null, top: int, ticks: list<array{value: int, pct: float}>, values: list<int>, previousValues: list<int>}>,
     *     labels: list<array{index: int, pct: float, date: string, minor: bool, anchor: string}>,
     *     dates: list<string>,
     * }
     */
    public static function build(array $series, array $metrics, ?array $previous = null): array
    {
        $count = count($series);
        $xs = [];
        foreach (array_keys($series) as $i) {
            $xs[] = $count > 1 ? $i / ($count - 1) : 0.5;
        }

        $layers = [];
        foreach ($metrics as $metric) {
            $values = array_map(fn (array $row): int => (int) $row[$metric], $series);
            $previousValues = $previous === null ? [] : array_map(fn (array $row): int => (int) $row[$metric], array_slice($previous, 0, $count));
            $top = self::niceTop(max([0, ...$values, ...$previousValues]));
            $points = self::points($values, $xs, $top);

            $ticks = self::ticks($top);

            $layers[$metric] = [
                'line' => self::path($points),
                'area' => $points === [] ? '' : self::path($points).' L'.end($points)[0].' '.self::HEIGHT.' L'.$points[0][0].' '.self::HEIGHT.' Z',
                'previous' => $previousValues === [] ? null : self::path(self::points($previousValues, $xs, $top)),
                'top' => $top,
                'ticks' => $ticks,
                'values' => $values,
                'previousValues' => $previousValues,
            ];
        }

        $dates = array_map(fn (array $row): string => (string) $row['date'], $series);

        return ['layers' => $layers, 'labels' => self::xLabels($dates), 'dates' => $dates];
    }

    /**
     * Plot points of daily values, spread evenly over the width ($xs: 0..1 per day).
     *
     * @param  list<int>  $values
     * @param  list<float|int>  $xs
     * @return list<array{0: float, 1: float}>
     */
    public static function points(array $values, array $xs, int $top): array
    {
        $points = [];
        foreach ($values as $i => $value) {
            $points[] = [round($xs[$i] * self::WIDTH, 1), round((1 - $value / max(1, $top)) * self::HEIGHT, 1)];
        }
        if (count($points) === 1) {
            // A single day: a flat line across the plot.
            $points = [[0.0, $points[0][1]], [(float) self::WIDTH, $points[0][1]]];
        }

        return $points;
    }

    /**
     * Date labels of the x axis: evenly spaced days, every other one marked minor (hidden on narrow screens).
     *
     * @param  list<string>  $dates
     * @return list<array{index: int, pct: float, date: string, minor: bool, anchor: string}>
     */
    public static function xLabels(array $dates): array
    {
        $count = count($dates);
        $labels = [];
        foreach (self::labelIndexes($count) as $n => $i) {
            $labels[] = [
                'index' => $i,
                'pct' => $count > 1 ? round($i / ($count - 1) * 100, 2) : 50.0,
                'date' => $dates[$i],
                'minor' => $n % 2 === 1 && $i !== $count - 1,
                'anchor' => $count === 1 ? 'middle' : ($i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle')),
            ];
        }

        return $labels;
    }

    /**
     * Ticks of the y axis from 0 to $top.
     *
     * @return list<array{value: int, pct: float}>
     */
    public static function ticks(int $top): array
    {
        $ticks = [];
        for ($t = 0; $t <= self::TICKS; $t++) {
            $ticks[] = ['value' => intdiv($top * $t, self::TICKS), 'pct' => round(100 - $t * 100 / self::TICKS, 2)];
        }

        return $ticks;
    }

    /**
     * Path of a sparkline in a 100 x 30 viewBox, or null when there are fewer than two values.
     *
     * @param  list<int|float>  $values
     */
    public static function sparkline(array $values): ?string
    {
        $count = count($values);
        if ($count < 2) {
            return null;
        }
        $max = max($values);
        $min = min($values);
        $span = $max - $min;
        $points = [];
        foreach ($values as $i => $value) {
            // 2 units of padding at the top and bottom keep the stroke inside the box; a flat series sits in the middle.
            $y = $span > 0 ? 28 - ($value - $min) / $span * 26 : 15;
            $points[] = [round($i / ($count - 1) * 100, 2), round($y, 2)];
        }

        return self::path($points);
    }

    /** @param list<array{0: float, 1: float}> $points */
    public static function path(array $points): string
    {
        $d = '';
        foreach ($points as [$x, $y]) {
            $d .= ($d === '' ? 'M' : ' L').$x.' '.$y;
        }

        return $d;
    }

    /**
     * Indexes of the days that get an x-axis label: every day up to MAX_X_LABELS days, otherwise every n-th day
     * counted back from the last one, so the labels are evenly spaced.
     *
     * @return list<int>
     */
    private static function labelIndexes(int $count): array
    {
        if ($count <= self::MAX_X_LABELS) {
            return $count === 0 ? [] : range(0, $count - 1);
        }
        $step = (int) ceil(($count - 1) / (self::MAX_X_LABELS - 1));

        return array_reverse(range($count - 1, 0, -$step));
    }

    /** Axis maximum: TICKS times a step of 1, 2 or 5 times a power of ten, at least the largest value. */
    public static function niceTop(int $max): int
    {
        $rough = max(1, $max) / self::TICKS;
        $magnitude = 10 ** (int) floor(log10($rough));
        foreach ([1, 2, 5, 10] as $factor) {
            $step = (int) max(1, $factor * $magnitude);
            if ($step * self::TICKS >= $max) {
                return $step * self::TICKS;
            }
        }

        return (int) (10 * $magnitude * self::TICKS);
    }
}
