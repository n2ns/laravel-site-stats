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
     * @return array{
     *     layers: array<string, array{line: string, area: string, top: int, ticks: list<array{value: int, pct: float}>, values: list<int>}>,
     *     labels: list<array{index: int, pct: float, date: string, minor: bool, anchor: string}>,
     *     dates: list<string>,
     * }
     */
    public static function build(array $series, array $metrics): array
    {
        $count = count($series);
        $xs = [];
        foreach (array_keys($series) as $i) {
            $xs[] = $count > 1 ? $i / ($count - 1) : 0.5;
        }

        $layers = [];
        foreach ($metrics as $metric) {
            $values = array_map(fn (array $row): int => (int) $row[$metric], $series);
            $top = self::niceTop($values === [] ? 0 : max($values));
            $points = [];
            foreach ($values as $i => $value) {
                $points[] = [round($xs[$i] * self::WIDTH, 1), round((1 - $value / $top) * self::HEIGHT, 1)];
            }
            if (count($points) === 1) {
                // A single day: a flat line across the plot.
                $points = [[0.0, $points[0][1]], [(float) self::WIDTH, $points[0][1]]];
            }

            $ticks = [];
            for ($t = 0; $t <= self::TICKS; $t++) {
                $value = intdiv($top * $t, self::TICKS);
                $ticks[] = ['value' => $value, 'pct' => round(100 - $t * 100 / self::TICKS, 2)];
            }

            $layers[$metric] = [
                'line' => self::path($points),
                'area' => $points === [] ? '' : self::path($points).' L'.end($points)[0].' '.self::HEIGHT.' L'.$points[0][0].' '.self::HEIGHT.' Z',
                'top' => $top,
                'ticks' => $ticks,
                'values' => $values,
            ];
        }

        $labels = [];
        foreach (self::labelIndexes($count) as $n => $i) {
            $labels[] = [
                'index' => $i,
                'pct' => round($xs[$i] * 100, 2),
                'date' => (string) $series[$i]['date'],
                'minor' => $n % 2 === 1 && $i !== $count - 1,
                'anchor' => $count === 1 ? 'middle' : ($i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle')),
            ];
        }

        return ['layers' => $layers, 'labels' => $labels, 'dates' => array_map(fn (array $row): string => (string) $row['date'], $series)];
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
    private static function path(array $points): string
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
    private static function niceTop(int $max): int
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
