{{--
    Inline SVG line chart of one metric at a time, with a metric selector (radio buttons: works without JS),
    a hover/keyboard tooltip (partials.scripts) and the data table. Needs: $series (rows with 'date' and one int
    per metric), $metrics (list of metric keys, at most 6), $title, $id; optionally $previous (the days just before,
    drawn as a dashed line).
--}}
@php
    use N2ns\Stats\Http\View\Dashboard;
    use N2ns\Stats\Http\View\Format;
    use N2ns\Stats\Http\View\LineChart;
    $chart = LineChart::build($series, $metrics, $previous ?? null);
    $labels = [];
    foreach ($metrics as $metric) {
        $labels[$metric] = __('stats::dashboard.metrics.'.$metric);
    }
@endphp
<section class="card chart" aria-labelledby="{{ $id }}-title" data-chart="{{ json_encode(Dashboard::tooltip($chart), JSON_UNESCAPED_UNICODE) }}">
@foreach ($metrics as $i => $metric)
<input type="radio" class="metric-radio m{{ $i }}" name="{{ $id }}-metric" id="{{ $id }}-metric-{{ $metric }}" value="{{ $metric }}" aria-describedby="{{ $id }}-metric-hint" @checked($i === 0)>
@endforeach
<div class="card-head">
<h2 id="{{ $id }}-title">{{ $title }}</h2>
<span class="sr-only" id="{{ $id }}-metric-hint">{{ __('stats::dashboard.chart.metric') }}: {{ $title }}</span>
<div class="metric-tabs">
@foreach ($metrics as $i => $metric)
<label for="{{ $id }}-metric-{{ $metric }}" class="t{{ $i }}">{{ $labels[$metric] }}</label>
@endforeach
</div>
@isset($previous)
<span class="prev-note">{{ __('stats::dashboard.chart.previous') }}</span>
@endisset
</div>
<div class="plot-wrap">
<div class="y-axis" aria-hidden="true">
@foreach ($metrics as $i => $metric)
<div class="layer l{{ $i }}">
@foreach ($chart['layers'][$metric]['ticks'] as $tick)
<span style="top:{{ $tick['pct'] }}%">{{ Format::compact($tick['value']) }}</span>
@endforeach
</div>
@endforeach
</div>
<div class="plot" tabindex="0" role="group" aria-label="{{ __('stats::dashboard.chart.explore', ['title' => $title]) }}">
<svg viewBox="0 0 {{ LineChart::WIDTH }} {{ LineChart::HEIGHT }}" role="img" preserveAspectRatio="none" aria-label="{{ __('stats::dashboard.chart.aria', ['metrics' => implode(' / ', $labels)]) }}">
@foreach (array_slice($chart['layers'][$metrics[0]]['ticks'], 1) as $tick)
<line class="grid" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" y2="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" vector-effect="non-scaling-stroke"/>
@endforeach
<line class="baseline" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ LineChart::HEIGHT }}" y2="{{ LineChart::HEIGHT }}" vector-effect="non-scaling-stroke"/>
@foreach ($metrics as $i => $metric)
<g class="layer l{{ $i }}" data-metric="{{ $metric }}">
@if ($chart['layers'][$metric]['previous'] !== null)
<path class="prev" d="{{ $chart['layers'][$metric]['previous'] }}" vector-effect="non-scaling-stroke"/>
@endif
<path class="area" d="{{ $chart['layers'][$metric]['area'] }}"/>
<path class="line" d="{{ $chart['layers'][$metric]['line'] }}" vector-effect="non-scaling-stroke"/>
</g>
@endforeach
</svg>
<div class="guide" hidden></div>
<div class="dot" hidden></div>
<div class="tip" role="status" aria-live="polite" hidden></div>
</div>
<div class="x-axis" aria-hidden="true">
@foreach ($chart['labels'] as $l)
<span class="{{ $l['anchor'] }}{{ $l['minor'] ? ' minor' : '' }}" style="left:{{ $l['pct'] }}%">{{ Format::date($l['date']) }}</span>
@endforeach
</div>
</div>
<details>
<summary>{{ __('stats::dashboard.chart.table') }}</summary>
<div class="table-scroll">
<table>
<caption class="sr-only">{{ $title }}</caption>
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
