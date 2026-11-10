@php
    use N2ns\Stats\Http\View\Format;
    $headers = [
        'path' => 'Page', 'pageviews' => 'Pageviews', 'visitors' => 'Visitors', 'entries' => 'Entries', 'exits' => 'Exits',
        'source_type' => 'Type', 'source' => 'Source', 'visits' => 'Visits', 'bounce_rate' => 'Bounce rate',
        'utm_medium' => 'Medium', 'utm_campaign' => 'Campaign', 'utm_content' => 'Content', 'utm_term' => 'Term',
        'value' => 'Value', 'target' => 'Target', 'count' => 'Count', 'name' => 'Name', 'label' => 'Label',
        'kind' => 'Kind',
    ];
    $columns = $rows === [] ? [] : array_keys($rows[0]);
@endphp
<div class="report">
<h3><span id="report-{{ $name }}">{{ $title }}</span>
@if ($rows !== [])
<a href="{{ route('stats.dashboard.export', ['publicId' => $site->public_id, 'report' => $name] + $query) }}" aria-label="Download {{ $title }} as CSV">CSV</a>
@endif
</h3>
@if ($rows === [])
<p class="muted">No data for this range.</p>
@else
<div class="table-scroll">
<table aria-labelledby="report-{{ $name }}">
<thead><tr>
@foreach ($columns as $c)
<th scope="col" class="{{ is_string($rows[0][$c]) || $rows[0][$c] === null ? '' : 'num' }}">{{ $headers[$c] ?? $c }}</th>
@endforeach
</tr></thead>
<tbody>
@foreach ($rows as $row)
<tr>
@foreach ($columns as $c)
@php $v = $row[$c]; @endphp
@if ($c === 'bounce_rate')
<td class="num">{{ Format::percent($v) }}</td>
@elseif (is_int($v) || is_float($v))
<td class="num">{{ Format::number($v) }}</td>
@else
<td class="key">{{ $v ?? '–' }}</td>
@endif
@endforeach
</tr>
@endforeach
</tbody>
</table>
</div>
@endif
</div>
