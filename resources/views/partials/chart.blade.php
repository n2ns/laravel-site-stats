@php use N2ns\Stats\Http\View\LineChart; @endphp
<div class="legend" aria-hidden="true"><span class="visitors">Visitors</span><span class="pageviews">Pageviews</span></div>
<svg viewBox="0 0 {{ LineChart::WIDTH }} {{ LineChart::HEIGHT }}" role="img" aria-label="Line chart of daily visitors and pageviews; the data table below lists the values.">
@foreach ($chart['grid'] as $g)
<line class="grid" x1="48" x2="{{ LineChart::WIDTH - 12 }}" y1="{{ $g['y'] }}" y2="{{ $g['y'] }}"/>
<text class="axis" x="42" y="{{ $g['y'] + 4 }}" text-anchor="end">{{ $g['label'] }}</text>
@endforeach
@foreach ($chart['labels'] as $l)
<text class="axis" x="{{ $l['x'] }}" y="{{ LineChart::HEIGHT - 8 }}" text-anchor="{{ $l['anchor'] }}">{{ $l['text'] }}</text>
@endforeach
@foreach ($chart['lines'] as $metric => $d)
<path class="l-{{ $metric }}" d="{{ $d }}"/>
@endforeach
@if (count($chart['points']) <= 92)
@foreach ($chart['points'] as $p)
@foreach ($p['values'] as $metric => $v)
<circle class="p-{{ $metric }}" cx="{{ $p['x'] }}" cy="{{ $v['y'] }}" r="3"><title>{{ $p['date'] }}: {{ number_format($v['value']) }} {{ $metric }}</title></circle>
@endforeach
@endforeach
@endif
</svg>
