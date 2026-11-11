<?php

namespace N2ns\Stats\Http\View;

/**
 * The world map of visitors per country: country shapes (and points for countries too small for the shapes) from
 * resources/data/world-map.json (built by scripts/build-world-map.php), shaded by their share of the busiest country.
 */
final class WorldMap
{
    /** @var array{width: int, height: int, countries: array<string, string>, points: array<string, array{0: float, 1: float}>}|null */
    private static ?array $map = null;

    /**
     * @param  array<string, int>  $visitors  alpha-2 country code => visitors
     * @return array{
     *     width: int,
     *     height: int,
     *     shapes: list<array{code: string, d: string, visitors: int, level: int}>,
     *     points: list<array{code: string, x: float, y: float, visitors: int, level: int}>,
     *     max: int,
     *     unmapped: int,
     * }
     */
    public static function build(array $visitors): array
    {
        $map = self::map();
        $max = $visitors === [] ? 0 : max($visitors);
        $level = fn (int $v): int => $v <= 0 || $max <= 0 ? 0 : (int) max(1, ceil(sqrt($v / $max) * 5));

        $shapes = [];
        foreach ($map['countries'] as $code => $d) {
            $v = $visitors[$code] ?? 0;
            $shapes[] = ['code' => $code, 'd' => $d, 'visitors' => $v, 'level' => $level($v)];
        }
        $points = [];
        foreach ($map['points'] as $code => [$x, $y]) {
            $v = $visitors[$code] ?? 0;
            if ($v > 0) { // small countries are drawn only when they had visitors
                $points[] = ['code' => $code, 'x' => $x, 'y' => $y, 'visitors' => $v, 'level' => $level($v)];
            }
        }
        $mapped = array_flip([...array_keys($map['countries']), ...array_keys($map['points'])]);

        return [
            'width' => $map['width'],
            'height' => $map['height'],
            'shapes' => $shapes,
            'points' => $points,
            'max' => $max,
            'unmapped' => array_sum(array_diff_key($visitors, $mapped)),
        ];
    }

    /** @return array{width: int, height: int, countries: array<string, string>, points: array<string, array{0: float, 1: float}>} */
    private static function map(): array
    {
        /** @var array{width: int, height: int, countries: array<string, string>, points: array<string, array{0: float, 1: float}>} */
        return self::$map ??= json_decode((string) file_get_contents(__DIR__.'/../../../resources/data/world-map.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
