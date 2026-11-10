@php
    use N2ns\Stats\Http\View\Format;
    $prev = $summary['previous'];
    $kpis = [
        ['Visitors', 'visitors', fn ($v) => Format::number($v), false],
        ['Visits', 'visits', fn ($v) => Format::number($v), false],
        ['Pageviews', 'pageviews', fn ($v) => Format::number($v), false],
        ['Views per visit', 'views_per_visit', fn ($v) => Format::number($v, 2), false],
        ['Bounce rate', 'bounce_rate', fn ($v) => Format::percent($v), true],
        ['Avg. visit duration', 'avg_duration_seconds', fn ($v) => Format::duration($v), false],
    ];
    $ranges = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom'];
    $titles = [
        'pages' => 'Top pages', 'entries' => 'Entry pages', 'exits' => 'Exit pages',
        'source_types' => 'Source types', 'sources' => 'Sources', 'campaigns' => 'UTM campaigns',
        'countries' => 'Countries', 'devices' => 'Devices', 'browsers' => 'Browsers', 'os' => 'Operating systems', 'languages' => 'Languages',
        'outbound' => 'Outbound links', 'downloads' => 'Downloads', 'events' => 'Custom events', 'ai' => 'AI referrals and fetchers',
    ];
@endphp
@extends('stats::layout')
@section('title', $site->name)
@section('content')
<section class="panel" aria-label="Site and date range">
<div class="toolbar">
<form method="get" action="{{ route('stats.dashboard') }}" class="toolbar" data-autosubmit>
<label>Site
<select name="site">
@foreach ($sites as $s)
<option value="{{ $s->public_id }}" @selected($s->id === $site->id)>{{ $s->name }}</option>
@endforeach
</select>
</label>
@foreach ($query as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<noscript><button type="submit">Go</button></noscript>
</form>
<form method="get" action="{{ route('stats.dashboard.site', ['publicId' => $site->public_id]) }}" class="toolbar" id="range-form">
<label>Range
<select name="range" id="range-select">
@foreach ($ranges as $value => $label)
<option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
@endforeach
</select>
</label>
<span class="toolbar" id="custom-range">
<label>From <input type="date" name="from" value="{{ $period->from->toDateString() }}"></label>
<label>To <input type="date" name="to" value="{{ $period->to->toDateString() }}"></label>
</span>
<button type="submit">Apply</button>
</form>
</div>
<p class="muted" style="margin:8px 0 0">
{{ $period->from->toDateString() }} – {{ $period->to->toDateString() }} ({{ $site->timezone }}),
compared with {{ $previous->from->toDateString() }} – {{ $previous->to->toDateString() }}.
</p>
@if ($rangeError)
<p class="error" role="alert">{{ $rangeError }} Showing the last 30 days instead.</p>
@endif
</section>

<section class="kpis" aria-label="Summary">
@foreach ($kpis as [$label, $key, $fmt, $lowerIsBetter])
@php $change = Format::change($summary[$key], $prev[$key]); @endphp
<div class="kpi">
<div class="label">{{ $label }}</div>
<div class="value">{{ $fmt($summary[$key]) }}</div>
<div class="delta {{ $change === null || $change == 0 ? 'muted' : (($change > 0) !== $lowerIsBetter ? 'up' : 'down') }}" title="Previous period: {{ $fmt($prev[$key]) }}">
{{ Format::signedPercent($change) }} <span class="sr-only">compared with the previous period ({{ $fmt($prev[$key]) }})</span>
</div>
</div>
@endforeach
<div class="kpi">
<div class="label">Realtime</div>
<div class="value">{{ Format::number($realtime) }}</div>
<div class="delta muted">visits active in the last 5 min</div>
</div>
</section>

<section class="panel chart" aria-labelledby="chart-title">
<h2 id="chart-title" style="margin:0 0 8px;font-size:15px">Daily visitors and pageviews</h2>
@include('stats::partials.chart', ['chart' => \N2ns\Stats\Http\View\LineChart::build($series)])
<details>
<summary class="muted">Data table</summary>
<div class="table-scroll">
<table>
<thead><tr><th scope="col">Date</th><th scope="col" class="num">Visitors</th><th scope="col" class="num">Visits</th><th scope="col" class="num">Pageviews</th></tr></thead>
<tbody>
@foreach ($series as $row)
<tr><td>{{ $row['date'] }}</td><td class="num">{{ Format::number($row['visitors']) }}</td><td class="num">{{ Format::number($row['visits']) }}</td><td class="num">{{ Format::number($row['pageviews']) }}</td></tr>
@endforeach
</tbody>
</table>
</div>
</details>
</section>

<section class="panel" aria-label="Reports">
<div class="tabs" role="tablist" hidden>
@foreach ($tabs as $tab => $names)
<button type="button" role="tab" id="tab-{{ $loop->index }}" aria-controls="panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}">{{ $tab }}</button>
@endforeach
</div>
@foreach ($tabs as $tab => $names)
<div class="reports" role="tabpanel" id="panel-{{ $loop->index }}" aria-labelledby="tab-{{ $loop->index }}">
@foreach ($names as $name)
@include('stats::partials.report', ['name' => $name, 'title' => $titles[$name], 'rows' => $reports[$name]])
@endforeach
</div>
@endforeach
</section>
@endsection

@push('scripts')
<script>
(function () {
  document.querySelectorAll('form[data-autosubmit] select').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });
  var select = document.getElementById('range-select'), custom = document.getElementById('custom-range');
  function toggle() { var on = select.value === 'custom'; custom.hidden = !on; custom.querySelectorAll('input').forEach(function (i) { i.disabled = !on; }); }
  select.addEventListener('change', function () { toggle(); if (select.value !== 'custom') select.form.submit(); });
  toggle();
  var list = document.querySelector('[role=tablist]');
  var tabs = Array.prototype.slice.call(list.querySelectorAll('[role=tab]'));
  function show(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { show(t); });
    t.addEventListener('keydown', function (e) {
      var n = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (n) { var next = tabs[(i + n + tabs.length) % tabs.length]; show(next); next.focus(); }
    });
  });
  list.hidden = false;
  show(tabs[0]);
})();
</script>
@endpush
