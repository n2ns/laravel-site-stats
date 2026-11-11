{{-- Inline SVG line chart with a data table. Needs: $series (rows with 'date' and one int per metric), $metrics (list of metric keys), $title, $id. --}}
@php
    use N2ns\Stats\Http\View\Format;
    use N2ns\Stats\Http\View\LineChart;
    $chart = LineChart::build($series, $metrics);
    $labels = [];
    foreach ($metrics as $metric) {
        $labels[$metric] = __('stats::dashboard.metrics.'.$metric);
    }
    $class = array_flip($metrics);
@endphp
<section class="panel chart" aria-labelledby="{{ $id }}-title">
<h2 id="{{ $id }}-title" style="margin:0 0 8px;font-size:15px">{{ $title }}</h2>
<ul class="legend" aria-hidden="true">
@foreach ($labels as $metric => $label)
<li class="s{{ $class[$metric] }}"><span class="swatch"></span><span class="name">{{ $label }}</span></li>
@endforeach
</ul>
<svg viewBox="0 0 {{ LineChart::WIDTH }} {{ LineChart::HEIGHT }}" role="img" aria-label="{{ __('stats::dashboard.chart.aria', ['metrics' => implode(' / ', $labels)]) }}">
@foreach ($chart['grid'] as $g)
<line class="grid" x1="48" x2="{{ LineChart::WIDTH - 12 }}" y1="{{ $g['y'] }}" y2="{{ $g['y'] }}"/>
<text class="axis" x="42" y="{{ $g['y'] + 4 }}" text-anchor="end">{{ $g['label'] }}</text>
@endforeach
@foreach ($chart['labels'] as $l)
<text class="axis" x="{{ $l['x'] }}" y="{{ LineChart::HEIGHT - 8 }}" text-anchor="{{ $l['anchor'] }}">{{ $l['text'] }}</text>
@endforeach
@foreach ($chart['lines'] as $metric => $d)
<path class="s{{ $class[$metric] }}" d="{{ $d }}"/>
@endforeach
@if (count($chart['points']) <= 92)
@foreach ($chart['points'] as $p)
@foreach ($p['values'] as $metric => $v)
<circle class="s{{ $class[$metric] }}" cx="{{ $p['x'] }}" cy="{{ $v['y'] }}" r="3"><title>{{ $p['date'] }}: {{ Format::number($v['value']) }} {{ $labels[$metric] }}</title></circle>
@endforeach
@endforeach
@endif
</svg>
<details>
<summary class="muted">{{ __('stats::dashboard.chart.table') }}</summary>
<div class="table-scroll">
<table>
<thead><tr><th scope="col">{{ __('stats::dashboard.chart.date') }}</th>@foreach ($labels as $label)<th scope="col" class="num">{{ $label }}</th>@endforeach</tr></thead>
<tbody>
@foreach ($series as $row)
<tr><td>{{ $row['date'] }}</td>@foreach ($metrics as $metric)<td class="num">{{ Format::number($row[$metric]) }}</td>@endforeach</tr>
@endforeach
</tbody>
</table>
</div>
</details>
</section>
