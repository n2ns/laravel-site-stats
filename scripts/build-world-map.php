<?php

/**
 * Builds resources/data/world-map.json, the country shapes of the dashboards' world map, from
 * world-atlas countries-110m.json (ISC licence, derived from Natural Earth, public domain) and
 * i18n-iso-countries codes.json (MIT licence; ISO 3166 numeric to alpha-2 codes) and
 * world-countries-centroids countries.csv (MIT licence, Gavin Rehkemper; a point for the countries too small for
 * the 110m shapes, such as Singapore and Hong Kong):
 *
 *   curl -sfLO https://cdn.jsdelivr.net/npm/world-atlas@2.0.2/countries-110m.json
 *   curl -sfLO https://cdn.jsdelivr.net/npm/i18n-iso-countries@7.11.3/codes.json
 *   curl -sfLO https://cdn.jsdelivr.net/gh/gavinr/world-countries-centroids@v1.0.0/dist/countries.csv
 *   php scripts/build-world-map.php countries-110m.json codes.json countries.csv
 *
 * Equirectangular projection, Antarctica left out; one SVG path per alpha-2 country code ("countries"), and
 * [x, y] for the countries without a shape ("points").
 */
[$script, $topoFile, $codesFile, $centroidsFile] = $argv + [null, null, null, null];
if ($topoFile === null || $codesFile === null || $centroidsFile === null) {
    fwrite(STDERR, "Usage: php {$script} countries-110m.json codes.json countries.csv\n");
    exit(1);
}

$topo = json_decode((string) file_get_contents($topoFile), true, flags: JSON_THROW_ON_ERROR);
$alpha2 = [];
foreach (json_decode((string) file_get_contents($codesFile), true, flags: JSON_THROW_ON_ERROR) as [$a2, , $numeric]) {
    $alpha2[$numeric] = $a2;
}
$byName = ['Kosovo' => 'XK']; // shapes without an ISO numeric code

const WIDTH = 1000;
const NORTH = 84.0;
const SOUTH = -58.0;
$k = WIDTH / 360;

[$sx, $sy] = $topo['transform']['scale'];
[$tx, $ty] = $topo['transform']['translate'];
$arcs = [];
foreach ($topo['arcs'] as $arc) {
    $x = 0;
    $y = 0;
    $points = [];
    foreach ($arc as [$dx, $dy]) {
        $x += $dx;
        $y += $dy;
        $points[] = [$x * $sx + $tx, $y * $sy + $ty];
    }
    $arcs[] = $points;
}

$ring = function (array $indexes) use ($arcs, $k): string {
    $points = [];
    foreach ($indexes as $i) {
        $arc = $i >= 0 ? $arcs[$i] : array_reverse($arcs[~$i]);
        if ($points !== []) {
            array_shift($arc); // shared with the end of the previous arc
        }
        array_push($points, ...$arc);
    }
    // A ring crossing the 180th meridian jumps from one edge of the map to the other: unwrap its longitudes so it stays
    // continuous (the part beyond the edge is cut off by the map's viewBox), and draw it again shifted by 360 degrees
    // so that part appears at the other edge.
    $unwrapped = [];
    $offset = 0;
    $previous = null;
    foreach ($points as [$lon, $lat]) {
        if ($previous !== null && abs($lon + $offset - $previous) > 180) {
            $offset += $lon + $offset < $previous ? 360 : -360;
        }
        $previous = $lon + $offset;
        $unwrapped[] = [$previous, $lat];
    }
    $lons = array_column($unwrapped, 0);
    $shifts = [0];
    if (max($lons) > 180) {
        $shifts[] = -360;
    }
    if (min($lons) < -180) {
        $shifts[] = 360;
    }

    $d = '';
    foreach ($shifts as $shift) {
        $last = null;
        $ring = '';
        foreach ($unwrapped as [$lon, $lat]) {
            $p = round(($lon + $shift + 180) * $k, 1).' '.round((NORTH - max(SOUTH, min(NORTH, $lat))) * $k, 1);
            if ($p !== $last) {
                $ring .= ($ring === '' ? 'M' : 'L').$p;
                $last = $p;
            }
        }
        $d .= $ring.'Z';
    }

    return $d;
};

$countries = [];
foreach ($topo['objects']['countries']['geometries'] as $geometry) {
    $code = isset($geometry['id']) ? ($alpha2[$geometry['id']] ?? null) : ($byName[$geometry['properties']['name'] ?? ''] ?? null);
    if ($code === null || $code === 'AQ') {
        continue;
    }
    $polygons = match ($geometry['type']) {
        'Polygon' => [$geometry['arcs']],
        'MultiPolygon' => $geometry['arcs'],
        default => [],
    };
    $d = '';
    foreach ($polygons as $polygon) {
        foreach ($polygon as $indexes) {
            $d .= $ring($indexes);
        }
    }
    $countries[$code] = ($countries[$code] ?? '').$d;
}
ksort($countries);

$points = [];
$csv = fopen($centroidsFile, 'r');
$header = fgetcsv($csv, escape: '');
while (($row = fgetcsv($csv, escape: '')) !== false) {
    $row = array_combine($header, $row);
    $code = $row['ISO'];
    if ($code === '' || $code === 'AQ' || isset($countries[$code]) || (float) $row['latitude'] < SOUTH) {
        continue;
    }
    $points[$code] = [round(((float) $row['longitude'] + 180) * $k, 1), round((NORTH - (float) $row['latitude']) * $k, 1)];
}
fclose($csv);
foreach (['HK' => [114.17, 22.32], 'MO' => [113.55, 22.19]] as $code => [$lon, $lat]) { // not separate in the centroids
    $points[$code] = [round(($lon + 180) * $k, 1), round((NORTH - $lat) * $k, 1)];
}
ksort($points);

$out = ['width' => WIDTH, 'height' => (int) round((NORTH - SOUTH) * $k), 'countries' => $countries, 'points' => $points];
file_put_contents(__DIR__.'/../resources/data/world-map.json', json_encode($out, JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, count($countries).' countries, '.count($points)." points\n");
