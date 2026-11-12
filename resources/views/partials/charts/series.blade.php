{{--
    Several daily series on one scale (Http\View\SeriesChart): stacked areas or separate lines, a legend with each
    series' total, and the data table. Needs: $chart with chart, dates, series, stacked.
--}}
@php
    use N2ns\Stats\Http\View\Format;
    use N2ns\Stats\Http\View\LineChart;
    $c = $chart['chart'];
@endphp
<ul class="stats-legend stats-inline">
@foreach ($c['layers'] as $i => $layer)
<li class="stats-s{{ $i % 6 }}"><span class="stats-swatch" aria-hidden="true"></span><span class="stats-name">{{ $layer['label'] }}</span> {{ Format::number($layer['total']) }}</li>
@endforeach
</ul>
<div class="stats-plot-wrap">
<div class="stats-y-axis" aria-hidden="true"><div class="stats-layer stats-static">
@foreach ($c['ticks'] as $tick)
<span style="top:{{ $tick['pct'] }}%">{{ Format::compact($tick['value']) }}</span>
@endforeach
</div></div>
<div class="stats-plot">
<svg viewBox="0 0 {{ LineChart::WIDTH }} {{ LineChart::HEIGHT }}" role="img" preserveAspectRatio="none" aria-label="{{ $chart['title'] }}">
@foreach (array_slice($c['ticks'], 1) as $tick)
<line class="stats-grid" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" y2="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" vector-effect="non-scaling-stroke"/>
@endforeach
<line class="stats-baseline" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ LineChart::HEIGHT }}" y2="{{ LineChart::HEIGHT }}" vector-effect="non-scaling-stroke"/>
@foreach ($c['layers'] as $i => $layer)
<g class="stats-series stats-s{{ $i % 6 }}"><title>{{ $layer['label'] }}: {{ Format::number($layer['total']) }}</title>
@if ($layer['area'] !== null)<path class="stats-band" d="{{ $layer['area'] }}"/>@endif
<path class="stats-stroke" d="{{ $layer['line'] }}" vector-effect="non-scaling-stroke"/>
</g>
@endforeach
</svg>
</div>
<div class="stats-x-axis" aria-hidden="true">
@foreach ($c['labels'] as $l)
<span class="stats-{{ $l['anchor'] }}{{ $l['minor'] ? ' stats-minor' : '' }}" style="left:{{ $l['pct'] }}%">{{ Format::date($l['date']) }}</span>
@endforeach
</div>
</div>
<details>
<summary>{{ __('stats::dashboard.chart.table') }}</summary>
<div class="stats-table-scroll">
<table>
<caption class="stats-sr-only">{{ $chart['title'] }}</caption>
<thead><tr><th scope="col">{{ __('stats::dashboard.chart.date') }}</th>@foreach ($c['layers'] as $layer)<th scope="col" class="stats-num">{{ $layer['label'] }}</th>@endforeach</tr></thead>
<tbody>
@foreach ($chart['dates'] as $d => $date)
<tr><td>{{ $date }}</td>@foreach ($c['layers'] as $layer)<td class="stats-num">{{ Format::number($chart['series'][$layer['name']][$d]) }}</td>@endforeach</tr>
@endforeach
</tbody>
</table>
</div>
</details>
