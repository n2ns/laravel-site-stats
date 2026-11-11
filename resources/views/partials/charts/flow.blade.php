{{-- Page flow (Http\View\FlowChart): pages moved from on the left, pages moved to on the right. Needs: $chart with flow. --}}
@php
    use N2ns\Stats\Http\View\FlowChart;
    use N2ns\Stats\Http\View\Format;
    $f = $chart['flow'];
@endphp
<div class="flow">
<svg viewBox="0 0 {{ FlowChart::WIDTH }} {{ $f['height'] }}" role="img" aria-label="{{ $chart['title'] }}">
@foreach ($f['links'] as $link)
<path class="link s{{ $link['color'] }}" d="{{ $link['d'] }}"><title>{{ $link['from'] }} → {{ $link['to'] }}: {{ Format::number($link['count']) }}</title></path>
@endforeach
@foreach ($f['nodes'] as $node)
<rect class="node" x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="10" height="{{ $node['h'] }}"><title>{{ $node['path'] }}: {{ Format::number($node['count']) }}</title></rect>
<text x="{{ $node['textX'] }}" y="{{ round($node['y'] + $node['h'] / 2, 1) }}" text-anchor="{{ $node['anchor'] }}" dominant-baseline="middle">{{ $node['label'] }} <tspan class="count">{{ Format::number($node['count']) }}</tspan><title>{{ $node['path'] }}</title></text>
@endforeach
</svg>
</div>
<details>
<summary>{{ __('stats::dashboard.chart.table') }}</summary>
<div class="table-scroll">
<table>
<caption class="sr-only">{{ $chart['title'] }}</caption>
<thead><tr><th scope="col">{{ __('stats::reports.columns.from_path') }}</th><th scope="col">{{ __('stats::reports.columns.to_path') }}</th><th scope="col" class="num">{{ __('stats::reports.columns.count') }}</th></tr></thead>
<tbody>
@foreach ($f['links'] as $link)
<tr><td>{{ $link['from'] }}</td><td>{{ $link['to'] }}</td><td class="num">{{ Format::number($link['count']) }}</td></tr>
@endforeach
</tbody>
</table>
</div>
</details>
