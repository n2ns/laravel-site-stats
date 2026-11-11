<?php

namespace N2ns\Stats\Http\View;

/**
 * Geometry of a chart of several daily series on one scale: stacked areas (parts of a whole, such as visitors per
 * source type) or separate lines (such as the count of each event). Same viewBox and axes as LineChart.
 */
final class SeriesChart
{
    /**
     * @param  list<string>  $dates
     * @param  array<string, list<int>>  $series  name => one value per date
     * @return array{
     *     layers: list<array{name: string, line: string, area: string|null, total: int}>,
     *     ticks: list<array{value: int, pct: float}>,
     *     labels: list<array{index: int, pct: float, date: string, minor: bool, anchor: string}>,
     * }
     */
    public static function build(array $dates, array $series, bool $stacked): array
    {
        $count = count($dates);
        $xs = [];
        foreach (array_keys($dates) as $i) {
            $xs[] = $count > 1 ? $i / ($count - 1) : 0.5;
        }

        $tops = array_fill(0, $count, 0);
        foreach ($series as $values) {
            foreach ($values as $i => $value) {
                $tops[$i] = $stacked ? $tops[$i] + $value : max($tops[$i], $value);
            }
        }
        $max = $tops === [] ? 0 : max($tops);
        $top = LineChart::niceTop($max);

        $layers = [];
        $base = array_fill(0, $count, 0);
        foreach ($series as $name => $values) {
            $upper = $stacked ? array_map(fn (int $b, int $v): int => $b + $v, $base, $values) : $values;
            $line = LineChart::points($upper, $xs, $top);
            $area = null;
            if ($stacked) {
                $lower = array_reverse(LineChart::points($base, $xs, $top));
                $area = LineChart::path([...$line, ...$lower]).' Z';
            }
            $layers[] = ['name' => (string) $name, 'line' => LineChart::path($line), 'area' => $area, 'total' => array_sum($values)];
            $base = $upper;
        }

        return ['layers' => $layers, 'ticks' => LineChart::ticks($top), 'labels' => LineChart::xLabels($dates)];
    }
}
