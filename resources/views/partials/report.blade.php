{{-- One report table with a share column, an optional donut and a CSV link. Needs: $name, $rows, $total, $site, $query. --}}
@php
    use N2ns\Stats\Http\View\Dashboard;
    use N2ns\Stats\Http\View\Format;
    use N2ns\Stats\Reports\StatsQuery;
    $title = __('stats::reports.titles.'.$name);
    $columns = StatsQuery::COLUMNS[$name];
    $metric = StatsQuery::mainMetric($name);
    $metricLabel = Dashboard::header($name, $metric);
@endphp
<div class="report{{ count($columns) >= 5 ? ' wide' : '' }}">
<h3><span id="report-{{ $name }}">{{ $title }}</span>
@if ($rows !== [])
<a href="{{ route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => $name] + $query) }}" aria-label="{{ __('stats::dashboard.csv_label', ['title' => $title]) }}">{{ __('stats::dashboard.csv') }}</a>
@endif
</h3>
@if ($rows === [])
<p class="muted">{{ __('stats::dashboard.no_data') }}</p>
@else
@if (in_array($name, Dashboard::DONUTS, true))
@include('stats::partials.donut', ['slices' => Dashboard::donut($name, $rows, $total), 'label' => __('stats::dashboard.donut', ['title' => $title, 'metric' => $metricLabel])])
@endif
<div class="table-scroll">
<table aria-labelledby="report-{{ $name }}" data-sortable>
<thead><tr>
@foreach ($columns as $c)
<th scope="col" class="{{ Dashboard::isText($c) ? '' : 'num' }}">{{ Dashboard::header($name, $c) }}</th>
@endforeach
<th scope="col" class="num" title="{{ __('stats::dashboard.share_of', ['metric' => $metricLabel]) }}">{{ __('stats::dashboard.share') }}</th>
</tr></thead>
<tbody>
@foreach ($rows as $row)
@php $share = $total > 0 ? (int) $row[$metric] / $total : 0.0; @endphp
<tr>
@foreach ($columns as $c)
@php $v = $row[$c]; @endphp
@if ($c === 'bounce_rate')
<td class="num" data-value="{{ $v }}">{{ Format::percent((float) $v) }}</td>
@elseif (! Dashboard::isText($c))
<td class="num" data-value="{{ $v }}">{{ Format::number((int) $v) }}</td>
@else
<td class="key">{{ Dashboard::text($name, $c, $v === null ? null : (string) $v) }}</td>
@endif
@endforeach
<td class="num share" data-value="{{ round($share, 6) }}">{{ Format::percent($share) }}<span class="bar" aria-hidden="true"><span style="width:{{ round($share * 100, 1) }}%"></span></span></td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif
</div>
