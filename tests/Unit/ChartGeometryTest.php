<?php

use N2ns\Stats\Http\View\FlowChart;
use N2ns\Stats\Http\View\LineChart;
use N2ns\Stats\Http\View\SeriesChart;
use N2ns\Stats\Http\View\WorldMap;

it('stacks series on one scale, the top of the stack at the daily total', function () {
    $chart = SeriesChart::build(['2026-03-01', '2026-03-02'], ['search' => [10, 30], 'direct' => [10, 10]], true);

    // The total of 40 sets the scale (40 is already a nice top): the second layer reaches it on the second day.
    expect($chart['ticks'][4]['value'])->toBe(40)
        ->and($chart['layers'][0])->toMatchArray(['name' => 'search', 'line' => 'M0 180 L800 60', 'total' => 40])
        ->and($chart['layers'][1]['line'])->toBe('M0 120 L800 0')
        ->and($chart['layers'][1]['area'])->toBe('M0 120 L800 0 L800 60 L0 180 Z') // down to the layer below
        ->and(array_column($chart['labels'], 'date'))->toBe(['2026-03-01', '2026-03-02']);

    $lines = SeriesChart::build(['2026-03-01', '2026-03-02'], ['a' => [10, 30], 'b' => [10, 10]], false);
    expect($lines['ticks'][4]['value'])->toBe(40) // the highest single value (30) on a nice scale
        ->and($lines['layers'][1]['line'])->toBe('M0 180 L800 180')
        ->and($lines['layers'][1]['area'])->toBeNull();
});

it('draws the previous period of the daily chart on the same scale', function () {
    $series = [['date' => '2026-03-01', 'visitors' => 10], ['date' => '2026-03-02', 'visitors' => 20]];
    $previous = [['date' => '2026-02-27', 'visitors' => 40], ['date' => '2026-02-28', 'visitors' => 0]];

    $layer = LineChart::build($series, ['visitors'], $previous)['layers']['visitors'];

    expect($layer['top'])->toBe(40)
        ->and($layer['previous'])->toBe('M0 0 L800 240')
        ->and($layer['previousValues'])->toBe([40, 0])
        ->and(LineChart::build($series, ['visitors'])['layers']['visitors']['previous'])->toBeNull();
});

it('lays out the page flow with bands as thick as their counts', function () {
    $flow = FlowChart::build([
        ['from_path' => '/', 'to_path' => '/pricing', 'count' => 6],
        ['from_path' => '/', 'to_path' => '/blog', 'count' => 2],
        ['from_path' => '/blog', 'to_path' => '/pricing', 'count' => 2],
    ]);

    $nodes = collect($flow['nodes'])->keyBy(fn ($n) => $n['side'].':'.$n['path']);
    expect($flow['height'])->toBe(60) // two rows
        ->and($nodes->keys()->all())->toBe(['from:/', 'from:/blog', 'to:/pricing', 'to:/blog'])
        ->and($nodes['from:/']['count'])->toBe(8)
        ->and($nodes['to:/pricing']['count'])->toBe(8)
        ->and($nodes['from:/']['h'])->toBe($nodes['to:/pricing']['h'])
        ->and(round($nodes['from:/']['h'] / $nodes['from:/blog']['h'], 1))->toBe(4.0)
        ->and($flow['links'])->toHaveCount(3)
        ->and($flow['links'][0])->toMatchArray(['from' => '/', 'to' => '/pricing', 'count' => 6, 'color' => 0])
        ->and($flow['links'][2]['color'])->toBe(1);

    expect(FlowChart::build([])['links'])->toBe([]);
});

it('shades the countries of the world map by visitors and adds points for small ones', function () {
    $map = WorldMap::build(['US' => 100, 'DE' => 25, 'FR' => 1, 'SG' => 4, 'XX' => 3]);

    $shapes = collect($map['shapes'])->keyBy('code');
    $points = collect($map['points'])->keyBy('code');
    expect($map['max'])->toBe(100)
        ->and([$shapes['US']['level'], $shapes['DE']['level'], $shapes['FR']['level'], $shapes['CN']['level']])->toBe([5, 3, 1, 0])
        ->and($shapes['US']['d'])->toStartWith('M')
        ->and($points->keys()->all())->toBe(['SG']) // small countries only when they had visitors
        ->and($points['SG'])->toMatchArray(['visitors' => 4, 'level' => 1])
        ->and($map['unmapped'])->toBe(3)
        ->and($map['width'] / $map['height'])->toBeGreaterThan(2.0);
});
