{{-- Dashboard content shared by the standalone dashboard and the Filament page (see Http\View\Dashboard::data()). --}}
@php
    use N2ns\Stats\Http\View\Dashboard;
    use N2ns\Stats\Http\View\Format;
    $comparison = $summary['comparison'];
    $kpis = [
        'visitors' => [fn ($v) => Format::number($v), false],
        'visits' => [fn ($v) => Format::number($v), false],
        'pageviews' => [fn ($v) => Format::number($v), false],
        'pages' => [fn ($v) => Format::number($v), false],
        'views_per_visit' => [fn ($v) => Format::number($v, 2), false],
        'bounce_rate' => [fn ($v) => Format::percent($v), true],
        'avg_duration_seconds' => [fn ($v) => Format::duration($v), false],
        'events' => [fn ($v) => Format::number($v), false],
        'outbound' => [fn ($v) => Format::number($v), false],
    ];
@endphp
<section class="panel" aria-label="{{ __('stats::dashboard.toolbar') }}">
<div class="toolbar">
<form method="get" action="{{ $siteAction }}" class="toolbar" data-autosubmit>
<label>{{ __('stats::dashboard.site') }}
<select name="site">
@foreach ($sites as $s)
<option value="{{ $s->public_id }}" @selected($s->id === $site->id)>{{ $s->name }}</option>
@endforeach
</select>
</label>
@foreach ($query as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<noscript><button type="submit">{{ __('stats::dashboard.go') }}</button></noscript>
</form>
<form method="get" action="{{ $rangeAction }}" class="toolbar" id="range-form">
@foreach ($rangeHidden as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<label>{{ __('stats::dashboard.range') }}
<select name="range" id="range-select">
@foreach ([...\N2ns\Stats\Reports\Period::PRESETS, 'custom'] as $value)
<option value="{{ $value }}" @selected($range === $value)>{{ __('stats::dashboard.ranges.'.$value) }}</option>
@endforeach
</select>
</label>
<span class="toolbar" id="custom-range">
<label>{{ __('stats::dashboard.from') }} <input type="date" name="from" value="{{ $period->from->toDateString() }}"></label>
<label>{{ __('stats::dashboard.to') }} <input type="date" name="to" value="{{ $period->to->toDateString() }}"></label>
</span>
<button type="submit">{{ __('stats::dashboard.apply') }}</button>
</form>
</div>
<p class="muted" style="margin:8px 0 0">
{{ $period->from->toDateString() }} – {{ $period->to->toDateString() }} ({{ $site->timezone }}).
{{ __('stats::dashboard.compared', ['current' => Dashboard::dates($comparison['current_from'], $comparison['current_to']), 'previous' => Dashboard::dates($comparison['previous_from'], $comparison['previous_to'])]) }}
</p>
@if ($summary['partial'])
<p class="muted" style="margin:4px 0 0">{{ __('stats::dashboard.partial') }}</p>
@endif
@if ($rangeError)
<p class="error" role="alert">{{ $rangeError }}</p>
@endif
</section>

<section class="kpis" aria-label="{{ __('stats::dashboard.summary') }}">
@foreach ($kpis as $key => [$fmt, $lowerIsBetter])
@php
    $current = $comparison['current'][$key];
    $previous = $comparison['previous'][$key];
    $change = Format::change($current, $previous);
@endphp
<div class="kpi">
<div class="label">{{ __('stats::dashboard.kpis.'.$key) }}</div>
<div class="value">{{ $fmt($summary[$key]) }}</div>
<div class="delta {{ $change === null || $change == 0 ? 'muted' : (($change > 0) !== $lowerIsBetter ? 'up' : 'down') }}" title="{{ __('stats::dashboard.delta_title', ['current' => $fmt($current), 'previous' => $fmt($previous)]) }}">
{{ Format::signedPercent($change) }} <span class="sr-only">{{ __('stats::dashboard.delta_sr', ['current' => $fmt($current), 'previous' => $fmt($previous)]) }}</span>
</div>
</div>
@endforeach
<div class="kpi">
<div class="label">{{ __('stats::dashboard.kpis.realtime') }}</div>
<div class="value">{{ Format::number($realtime) }}</div>
<div class="delta muted">{{ __('stats::dashboard.realtime_note') }}</div>
</div>
</section>

@include('stats::partials.chart', ['series' => $series, 'metrics' => Dashboard::CHART_METRICS, 'title' => __('stats::dashboard.chart.title'), 'id' => 'chart'])

<section class="panel" aria-label="{{ __('stats::dashboard.reports') }}">
<div class="tabs" role="tablist" hidden>
@foreach ($tabs as $tab => $names)
<button type="button" role="tab" id="tab-{{ $tab }}" aria-controls="panel-{{ $tab }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}">{{ __('stats::dashboard.tabs.'.$tab) }}</button>
@endforeach
</div>
@foreach ($tabs as $tab => $names)
<div role="tabpanel" id="panel-{{ $tab }}" aria-labelledby="tab-{{ $tab }}">
<h2 class="sr-only">{{ __('stats::dashboard.tabs.'.$tab) }}</h2>
@if ($tab === 'ai')
<div class="kpis" aria-label="{{ __('stats::dashboard.ai.summary') }}">
<div class="kpi"><div class="label">{{ __('stats::dashboard.ai.visits') }}</div><div class="value">{{ Format::number($ai['visits']) }}</div><div class="delta muted">{{ __('stats::dashboard.ai.visits_note') }}</div></div>
<div class="kpi"><div class="label">{{ __('stats::dashboard.ai.visitors') }}</div><div class="value">{{ Format::number($ai['visitors']) }}</div></div>
<div class="kpi"><div class="label">{{ __('stats::dashboard.ai.fetches') }}</div><div class="value">{{ Format::number($ai['fetches']) }}</div><div class="delta muted">{{ __('stats::dashboard.ai.fetches_note') }}</div></div>
</div>
@include('stats::partials.chart', ['series' => $ai['series'], 'metrics' => ['visits', 'visitors', 'fetches'], 'title' => __('stats::dashboard.ai.chart'), 'id' => 'ai-chart'])
@endif
<div class="reports">
@foreach ($names as $name)
@include('stats::partials.report', ['name' => $name, 'rows' => $reports[$name], 'total' => $totals[$name]])
@endforeach
</div>
</div>
@endforeach
</section>
