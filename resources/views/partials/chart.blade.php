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
<section class="stats-card stats-chart" aria-labelledby="{{ $id }}-title" data-chart="{{ json_encode(Dashboard::tooltip($chart), JSON_UNESCAPED_UNICODE) }}">
@foreach ($metrics as $i => $metric)
<input type="radio" class="stats-metric-radio stats-m{{ $i }}" name="{{ $id }}-metric" id="{{ $id }}-metric-{{ $metric }}" value="{{ $metric }}" aria-describedby="{{ $id }}-metric-hint" @checked($i === 0)>
@endforeach
<div class="stats-card-head">
<h2 id="{{ $id }}-title">{{ $title }}</h2>
<span class="stats-sr-only" id="{{ $id }}-metric-hint">{{ __('stats::dashboard.chart.metric') }}: {{ $title }}</span>
<div class="stats-metric-tabs">
@foreach ($metrics as $i => $metric)
<label for="{{ $id }}-metric-{{ $metric }}" class="stats-t{{ $i }}">{{ $labels[$metric] }}</label>
@endforeach
</div>
@isset($previous)
<span class="stats-prev-note">{{ __('stats::dashboard.chart.previous') }}</span>
@endisset
</div>
<div class="stats-plot-wrap">
<div class="stats-y-axis" aria-hidden="true">
@foreach ($metrics as $i => $metric)
<div class="stats-layer stats-l{{ $i }}">
@foreach ($chart['layers'][$metric]['ticks'] as $tick)
<span style="top:{{ $tick['pct'] }}%">{{ Format::compact($tick['value']) }}</span>
@endforeach
</div>
@endforeach
</div>
<div class="stats-plot" tabindex="0" role="group" aria-label="{{ __('stats::dashboard.chart.explore', ['title' => $title]) }}">
<svg viewBox="0 0 {{ LineChart::WIDTH }} {{ LineChart::HEIGHT }}" role="img" preserveAspectRatio="none" aria-label="{{ __('stats::dashboard.chart.aria', ['metrics' => implode(' / ', $labels)]) }}">
@foreach (array_slice($chart['layers'][$metrics[0]]['ticks'], 1) as $tick)
<line class="stats-grid" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" y2="{{ round($tick['pct'] * LineChart::HEIGHT / 100, 1) }}" vector-effect="non-scaling-stroke"/>
@endforeach
<line class="stats-baseline" x1="0" x2="{{ LineChart::WIDTH }}" y1="{{ LineChart::HEIGHT }}" y2="{{ LineChart::HEIGHT }}" vector-effect="non-scaling-stroke"/>
@foreach ($metrics as $i => $metric)
<g class="stats-layer stats-l{{ $i }}" data-metric="{{ $metric }}">
@if ($chart['layers'][$metric]['previous'] !== null)
<path class="stats-prev" d="{{ $chart['layers'][$metric]['previous'] }}" vector-effect="non-scaling-stroke"/>
@endif
<path class="stats-area" d="{{ $chart['layers'][$metric]['area'] }}"/>
<path class="stats-line" d="{{ $chart['layers'][$metric]['line'] }}" vector-effect="non-scaling-stroke"/>
</g>
@endforeach
</svg>
<div class="stats-guide" hidden></div>
<div class="stats-dot" hidden></div>
<div class="stats-tip" role="status" aria-live="polite" hidden></div>
</div>
<div class="stats-x-axis" aria-hidden="true">
@foreach ($chart['labels'] as $l)
<span class="stats-{{ $l['anchor'] }}{{ $l['minor'] ? ' stats-minor' : '' }}" style="left:{{ $l['pct'] }}%">{{ Format::date($l['date']) }}</span>
@endforeach
</div>
</div>
<details>
<summary>{{ __('stats::dashboard.chart.table') }}</summary>
<div class="stats-table-scroll">
<table>
<caption class="stats-sr-only">{{ $title }}</caption>
<thead><tr><th scope="col">{{ __('stats::dashboard.chart.date') }}</th>@foreach ($labels as $label)<th scope="col" class="stats-num">{{ $label }}</th>@endforeach</tr></thead>
<tbody>
@foreach ($series as $row)
<tr><td>{{ $row['date'] }}</td>@foreach ($metrics as $metric)<td class="stats-num">{{ Format::number($row[$metric]) }}</td>@endforeach</tr>
@endforeach
</tbody>
</table>
</div>
</details>
</section>
