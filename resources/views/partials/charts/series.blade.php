{{--
    Several daily series on one scale (Http\View\SeriesChart): stacked areas or separate lines, a legend with each
    series' total, and the data table. Needs: $chart with chart, dates, series, stacked.
--}}
@php
    use N2ns\Stats\Http\View\Format;
    use N2ns\Stats\Http\View\LineChart;
    $c = $chart['chart'];
@endphp
<ul class="legend inline">
@foreach ($c['layers'] as $i => $layer)
<li class="s{{ $i % 6 }}"><span class="swatch" aria-hidden="true"></span><span class="name">{{ $layer['label'] }}</span> {{ Format::number($layer['total']) }}</li>
@endforeach
</ul>
<div class="plot-wrap">
<div class="y-axis" aria-hidden="true"><div class="layer static">
@foreach ($c['ticks'] as $tick)
<span style="top:{{ $tick['pct'] }}%">{{ Format::compact($tick['value']) }}</span>
@endforeach
</div></div>
<div class="plot">
<svg viewBox="0 0 {{ LineChart::WIDTH }} {{ LineChart::HEIGHT }}" role="img" preserveAspectRatio="none" aria-label="{{ $chart['title'] }}">
@foreach (array_slice($c['ticks'], 1) as $tick)
<line class="grid" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" y2="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" vector-effect="non-scaling-stroke"/>
@endforeach
<line class="baseline" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ LineChart::HEIGHT }}" y2="{{ LineChart::HEIGHT }}" vector-effect="non-scaling-stroke"/>
@foreach ($c['layers'] as $i => $layer)
<g class="series s{{ $i % 6 }}"><title>{{ $layer['label'] }}: {{ Format::number($layer['total']) }}</title>
@if ($layer['area'] !== null)<path class="band" d="{{ $layer['area'] }}"/>@endif
<path class="stroke" d="{{ $layer['line'] }}" vector-effect="non-scaling-stroke"/>
</g>
@endforeach
</svg>
</div>
<div class="x-axis" aria-hidden="true">
@foreach ($c['labels'] as $l)
<span class="{{ $l['anchor'] }}{{ $l['minor'] ? ' minor' : '' }}" style="left:{{ $l['pct'] }}%">{{ Format::date($l['date']) }}</span>
@endforeach
</div>
</div>
<details>
<summary>{{ __('stats::dashboard.chart.table') }}</summary>
<div class="table-scroll">
<table>
<caption class="sr-only">{{ $chart['title'] }}</caption>
<thead><tr><th scope="col">{{ __('stats::dashboard.chart.date') }}</th>@foreach ($c['layers'] as $layer)<th scope="col" class="num">{{ $layer['label'] }}</th>@endforeach</tr></thead>
<tbody>
@foreach ($chart['dates'] as $d => $date)
<tr><td>{{ $date }}</td>@foreach ($c['layers'] as $layer)<td class="num">{{ Format::number($chart['series'][$layer['name']][$d]) }}</td>@endforeach</tr>
@endforeach
</tbody>
</table>
</div>
</details>
