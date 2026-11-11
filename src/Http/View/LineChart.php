<?php

namespace N2ns\Stats\Http\View;

/** Geometry for the dashboard's inline SVG line chart (no JavaScript). */
final class LineChart
{
    public const WIDTH = 800;

    public const HEIGHT = 240;

    private const LEFT = 48;

    private const RIGHT = 12;

    private const TOP = 12;

    private const BOTTOM = 28;

    /**
     * @param  list<array<string, int|string>>  $series  one row per day: 'date' and an int per metric
     * @param  list<string>  $metrics
     * @return array{lines: array<string, string>, points: list<array{x: float, values: array<string, array{y: float, value: int}>, date: string}>, grid: list<array{y: float, label: string}>, labels: list<array{x: float, text: string, anchor: string}>, max: int}
     */
    public static function build(array $series, array $metrics): array
    {
        $max = 0;
        foreach ($series as $row) {
            foreach ($metrics as $metric) {
                $max = max($max, (int) $row[$metric]);
            }
        }
        $max = self::niceMax($max);

        $plotWidth = self::WIDTH - self::LEFT - self::RIGHT;
        $plotHeight = self::HEIGHT - self::TOP - self::BOTTOM;
        $count = count($series);
        $step = $count > 1 ? $plotWidth / ($count - 1) : 0;

        $points = [];
        $lines = array_fill_keys($metrics, '');
        foreach ($series as $i => $row) {
            $x = round(self::LEFT + ($count > 1 ? $i * $step : $plotWidth / 2), 1);
            $values = [];
            foreach ($metrics as $metric) {
                $value = (int) $row[$metric];
                $y = round(self::TOP + $plotHeight - ($value / $max) * $plotHeight, 1);
                $values[$metric] = ['y' => $y, 'value' => $value];
                $lines[$metric] .= ($lines[$metric] === '' ? 'M' : ' L').$x.' '.$y;
            }
            $points[] = ['x' => $x, 'values' => $values, 'date' => (string) $row['date']];
        }

        $grid = [];
        foreach ([0, 0.5, 1] as $fraction) {
            $grid[] = [
                'y' => round(self::TOP + $plotHeight - $fraction * $plotHeight, 1),
                'label' => number_format((int) round($max * $fraction)),
            ];
        }

        $labels = [];
        if ($count > 0) {
            $labels[] = ['x' => $points[0]['x'], 'text' => (string) $series[0]['date'], 'anchor' => $count > 1 ? 'start' : 'middle'];
        }
        if ($count > 2) {
            $mid = intdiv($count, 2);
            $labels[] = ['x' => $points[$mid]['x'], 'text' => (string) $series[$mid]['date'], 'anchor' => 'middle'];
        }
        if ($count > 1) {
            $labels[] = ['x' => $points[$count - 1]['x'], 'text' => (string) $series[$count - 1]['date'], 'anchor' => 'end'];
        }

        return ['lines' => $lines, 'points' => $points, 'grid' => $grid, 'labels' => $labels, 'max' => $max];
    }

    /** Rounds the axis maximum up to 1, 2 or 5 times a power of ten (at least 1). */
    private static function niceMax(int $value): int
    {
        if ($value <= 1) {
            return 1;
        }
        $magnitude = 10 ** (int) floor(log10($value));
        foreach ([1, 2, 5, 10] as $factor) {
            if ($factor * $magnitude >= $value) {
                return $factor * $magnitude;
            }
        }

        return 10 * $magnitude;
    }
}
