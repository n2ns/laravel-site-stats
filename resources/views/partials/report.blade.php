{{--
    One report card: an optional donut, a compact table (label, main metric, share; the top rows with
    "show all") and, for reports with more columns, a details view with the full sortable table.
    Needs: $name, $rows, $total, $site, $query.
--}}
@php
    use N2ns\Stats\Http\View\Dashboard;
    use N2ns\Stats\Http\View\Format;
    use N2ns\Stats\Reports\StatsQuery;
    $title = __('stats::reports.titles.'.$name);
    $columns = StatsQuery::COLUMNS[$name];
    $layout = Dashboard::layout($name);
    $metric = $layout['metric'];
    $metricLabel = Dashboard::header($name, $metric);
    $top = Dashboard::TOP_ROWS;
@endphp
<article class="stats-card stats-report" id="card-{{ $name }}">
<div class="stats-card-head">
<h3 id="report-{{ $name }}">{{ $title }}</h3>
@if ($rows !== [])
<a href="{{ route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => $name] + $query) }}" aria-label="{{ __('stats::dashboard.csv_label', ['title' => $title]) }}">{{ __('stats::dashboard.csv') }}</a>
@endif
</div>
@if ($rows === [])
<p class="stats-empty">{{ __('stats::dashboard.no_data') }}</p>
@else
@if (in_array($name, Dashboard::DONUTS, true))
@include('stats::partials.donut', ['slices' => Dashboard::donut($name, $rows, $total), 'label' => __('stats::dashboard.donut', ['title' => $title, 'metric' => $metricLabel])])
@endif
<table class="stats-compact" aria-labelledby="report-{{ $name }}" data-sortable data-top="{{ $top }}">
<thead><tr>
<th scope="col">{{ Dashboard::header($name, $layout['label']) }}</th>
<th scope="col" class="stats-num">{{ $metricLabel }}</th>
<th scope="col" class="stats-num" title="{{ __('stats::dashboard.share_of', ['metric' => $metricLabel]) }}">{{ __('stats::dashboard.share') }}</th>
</tr></thead>
<tbody>
@foreach ($rows as $row)
@php
    $share = $total > 0 ? (int) $row[$metric] / $total : 0.0;
    $sub = array_values(array_filter(array_map(fn ($c) => $row[$c] === null || $row[$c] === '' ? null : Dashboard::cell($name, $c, $row[$c]), $layout['sub']), fn ($t) => $t !== null));
@endphp
<tr @class(['stats-more' => $loop->index >= $top])>
<td class="stats-key"><div class="stats-bar-row"><span class="stats-fill" style="width:{{ round($share * 100, 1) }}%" aria-hidden="true"></span><span class="stats-lbl">{{ Dashboard::cell($name, $layout['label'], $row[$layout['label']]) }}</span>@if ($sub !== [])<span class="stats-sub">{{ implode(' · ', $sub) }}</span>@endif</div></td>
<td class="stats-num" data-value="{{ $row[$metric] }}">{{ Dashboard::cell($name, $metric, $row[$metric]) }}</td>
<td class="stats-num" data-value="{{ round($share, 6) }}">{{ Format::percent($share) }}</td>
</tr>
@endforeach
</tbody>
</table>
@if (count($rows) > $top || $layout['details'])
<div class="stats-card-foot">
@if (count($rows) > $top)
<button type="button" class="stats-link stats-more-toggle" aria-expanded="false" data-more="{{ __('stats::dashboard.show_all', ['count' => count($rows)]) }}" data-less="{{ __('stats::dashboard.show_top', ['count' => $top]) }}" hidden>{{ __('stats::dashboard.show_all', ['count' => count($rows)]) }}</button>
@endif
@if ($layout['details'])
<details class="stats-details">
<summary>{{ __('stats::dashboard.details') }}</summary>
<div class="stats-table-scroll">
<table aria-label="{{ __('stats::dashboard.details_table', ['title' => $title]) }}" data-sortable>
<thead><tr>
@foreach ($columns as $c)
<th scope="col" class="{{ Dashboard::isText($c) ? '' : 'stats-num' }}">{{ Dashboard::header($name, $c) }}</th>
@endforeach
<th scope="col" class="stats-num" title="{{ __('stats::dashboard.share_of', ['metric' => $metricLabel]) }}">{{ __('stats::dashboard.share') }}</th>
</tr></thead>
<tbody>
@foreach ($rows as $row)
@php $share = $total > 0 ? (int) $row[$metric] / $total : 0.0; @endphp
<tr>
@foreach ($columns as $c)
@if (Dashboard::isText($c))
<td class="stats-key">{{ Dashboard::cell($name, $c, $row[$c]) }}</td>
@else
<td class="stats-num" data-value="{{ $row[$c] }}">{{ Dashboard::cell($name, $c, $row[$c]) }}</td>
@endif
@endforeach
<td class="stats-num" data-value="{{ round($share, 6) }}">{{ Format::percent($share) }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
</details>
@endif
</div>
@endif
@endif
</article>
