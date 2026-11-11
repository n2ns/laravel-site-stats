<?php

namespace N2ns\Stats\Http\View;

/**
 * Geometry of the page flow chart: the pages visitors came from on the left, the pages they went to on the right,
 * and a band for each move whose thickness is its count. Drawn in a WIDTH-wide viewBox that keeps its aspect ratio.
 */
final class FlowChart
{
    public const WIDTH = 800;

    /** x of the left and right node bars, and their width. */
    private const LEFT = 250;

    private const RIGHT = 540;

    private const BAR = 10;

    private const GAP = 10;

    /** Height per node row; the chart grows with the number of pages on its longer side. */
    private const ROW = 30;

    private const MAX_LABEL = 34;

    /**
     * @param  list<array<string, int|float|string|null>>  $moves  rows of the transitions report (from_path, to_path, count)
     * @return array{
     *     height: int,
     *     nodes: list<array{side: string, label: string, path: string, x: int, y: float, h: float, count: int, textX: int, anchor: string}>,
     *     links: list<array{d: string, from: string, to: string, count: int, color: int}>,
     * }
     */
    public static function build(array $moves): array
    {
        $sides = ['from' => [], 'to' => []];
        $total = 0;
        foreach ($moves as $move) {
            $count = (int) $move['count'];
            $total += $count;
            $sides['from'][(string) $move['from_path']] = ($sides['from'][(string) $move['from_path']] ?? 0) + $count;
            $sides['to'][(string) $move['to_path']] = ($sides['to'][(string) $move['to_path']] ?? 0) + $count;
        }
        foreach ($sides as &$nodes) {
            arsort($nodes);
        }
        unset($nodes);

        $rows = max(count($sides['from']), count($sides['to']), 1);
        $height = $rows * self::ROW;
        $scale = $total > 0 ? min(array_map(fn (array $n): float => ($height - self::GAP * (count($n) - 1)) / $total, array_values($sides))) : 0.0;

        $nodes = [];
        $tops = [];
        foreach ($sides as $side => $counts) {
            $span = array_sum($counts) * $scale + self::GAP * (count($counts) - 1);
            $y = ($height - $span) / 2;
            foreach ($counts as $path => $count) {
                $h = max(1.0, $count * $scale);
                $left = $side === 'from';
                $nodes[] = [
                    'side' => $side,
                    'label' => self::label((string) $path),
                    'path' => (string) $path,
                    'x' => $left ? self::LEFT - self::BAR : self::RIGHT,
                    'y' => round($y, 1),
                    'h' => round($h, 1),
                    'count' => $count,
                    'textX' => $left ? self::LEFT - self::BAR - 6 : self::RIGHT + self::BAR + 6,
                    'anchor' => $left ? 'end' : 'start',
                ];
                $tops[$side][(string) $path] = $y;
                $y += $h + self::GAP;
            }
        }

        $links = [];
        $fromRank = array_flip(array_map('strval', array_keys($sides['from'])));
        foreach ($moves as $move) {
            [$from, $to, $count] = [(string) $move['from_path'], (string) $move['to_path'], (int) $move['count']];
            $h = max(1.0, $count * $scale);
            $y0 = $tops['from'][$from];
            $y1 = $tops['to'][$to];
            $tops['from'][$from] += $h;
            $tops['to'][$to] += $h;
            [$x0, $x1] = [self::LEFT, self::RIGHT];
            $mid = ($x0 + $x1) / 2;
            $f = fn (float $v): string => (string) round($v, 1);
            $d = 'M'.$x0.' '.$f($y0).' C'.$mid.' '.$f($y0).' '.$mid.' '.$f($y1).' '.$x1.' '.$f($y1)
                .' L'.$x1.' '.$f($y1 + $h).' C'.$mid.' '.$f($y1 + $h).' '.$mid.' '.$f($y0 + $h).' '.$x0.' '.$f($y0 + $h).' Z';
            $links[] = ['d' => $d, 'from' => $from, 'to' => $to, 'count' => $count, 'color' => $fromRank[$from] % 5];
        }

        return ['height' => $height, 'nodes' => $nodes, 'links' => $links];
    }

    private static function label(string $path): string
    {
        return mb_strlen($path) > self::MAX_LABEL ? mb_substr($path, 0, self::MAX_LABEL - 1).'…' : $path;
    }
}
