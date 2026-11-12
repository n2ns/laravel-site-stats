{{-- Dashboard content (see Http\View\Dashboard::data()): toolbar, KPI strip, daily chart and the report tabs. --}}
@php
    use N2ns\Stats\Http\View\Dashboard;
    use N2ns\Stats\Http\View\Format;
    $comparison = $summary['comparison'];
@endphp
<h1 class="stats-sr-only">{{ $site->name }}</h1>
<section aria-label="{{ __('stats::dashboard.toolbar') }}">
<div class="stats-toolbar">
@if ($sites->count() === 1)
{{-- A single site (the stats of the app itself): its name, no switcher. --}}
<div class="stats-site-switch"><span class="stats-site-name">{{ $site->name }}</span></div>
@else
<form method="get" action="{{ $siteAction }}" class="stats-site-switch" data-autosubmit>
<label>{{ __('stats::dashboard.site') }}
{{-- Browsers size a select by its options in normal weight; the bold title needs room for the longest name. --}}
<select name="site" style="min-width:calc({{ max(4, ...$sites->map(fn ($s) => mb_strlen($s->name))->all()) }}ch + 3.5em)">
@foreach ($sites as $s)
<option value="{{ $s->public_id }}" @selected($s->id === $site->id)>{{ $s->name }}</option>
@endforeach
</select>
</label>
@foreach ($query as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<noscript><button type="submit">{{ __('stats::dashboard.go') }}</button></noscript>
</form>
@endif
<form method="get" action="{{ $rangeAction }}" class="stats-range-form" id="range-form">
@foreach ($rangeHidden as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<label>{{ __('stats::dashboard.range') }}
<select name="range" id="range-select">
@foreach ([...\N2ns\Stats\Reports\Period::PRESETS, 'custom'] as $value)
<option value="{{ $value }}" @selected($range === $value)>{{ __('stats::dashboard.ranges.'.$value) }}</option>
@endforeach
</select>
</label>
<span class="stats-custom" id="custom-range">
<label>{{ __('stats::dashboard.from') }} <input type="date" name="from" value="{{ $period->from->toDateString() }}"></label>
<label>{{ __('stats::dashboard.to') }} <input type="date" name="to" value="{{ $period->to->toDateString() }}"></label>
</span>
<button type="submit">{{ __('stats::dashboard.apply') }}</button>
</form>
</div>
<div class="stats-period">
<p><strong>{{ $period->from->toDateString() }} – {{ $period->to->toDateString() }} ({{ $site->timezone }})</strong> · {{ __('stats::dashboard.compared', ['current' => Dashboard::dates($comparison['current_from'], $comparison['current_to']), 'previous' => Dashboard::dates($comparison['previous_from'], $comparison['previous_to'])]) }}</p>
@if ($summary['partial'])
<p>{{ __('stats::dashboard.partial') }}</p>
@endif
@if ($rangeError)
<p class="stats-alert" role="alert">{{ $rangeError }}</p>
@endif
</div>
</section>

<section aria-label="{{ __('stats::dashboard.summary') }}">
@foreach ([true, false] as $primary)
<div class="stats-kpis stats-{{ $primary ? 'primary' : 'secondary' }}"@if (! $primary) role="group" aria-label="{{ __('stats::dashboard.more_metrics') }}"@endif>
@foreach ($kpis as $kpi)
@continue($kpi['primary'] !== $primary)
<div class="stats-kpi" data-kpi="{{ $kpi['key'] }}">
<div class="stats-label">{{ __('stats::dashboard.kpis.'.$kpi['key']) }}</div>
<div class="stats-value">{{ $kpi['value'] }}</div>
<div class="stats-foot">
<div class="stats-delta stats-{{ $kpi['tone'] }}" title="{{ __('stats::dashboard.delta_title', ['current' => $kpi['current'], 'previous' => $kpi['previous']]) }}"><span aria-hidden="true">{{ ['up' => '↑', 'down' => '↓', 'flat' => ''][$kpi['direction']] }}</span> {{ $kpi['delta'] }} <span class="stats-sr-only">{{ __('stats::dashboard.delta_sr', ['current' => $kpi['current'], 'previous' => $kpi['previous']]) }}</span>@if ($primary)<span class="stats-vs" aria-hidden="true"> {{ __('stats::dashboard.vs_previous') }}</span>@endif</div>
@if ($kpi['spark'] !== null)
<svg class="stats-spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="{{ $kpi['spark'] }}" vector-effect="non-scaling-stroke"/></svg>
@endif
</div>
</div>
@endforeach
@if (! $primary)
<div class="stats-kpi" data-kpi="realtime">
<div class="stats-label">{{ __('stats::dashboard.kpis.realtime') }}</div>
<div class="stats-value"><span class="stats-live" aria-hidden="true"></span>{{ Format::number($realtime) }}</div>
<div class="stats-foot"><span class="stats-note">{{ __('stats::dashboard.realtime_note') }}</span></div>
</div>
@endif
</div>
@endforeach
</section>

@include('stats::partials.chart', ['series' => $series, 'previous' => $previousSeries, 'metrics' => Dashboard::CHART_METRICS, 'title' => __('stats::dashboard.chart.title'), 'id' => 'chart'])

<section aria-label="{{ __('stats::dashboard.reports') }}">
<div class="stats-tabs" role="tablist" aria-label="{{ __('stats::dashboard.reports') }}" hidden>
@foreach ($tabs as $tab => $names)
<button type="button" role="tab" id="tab-{{ $tab }}" aria-controls="panel-{{ $tab }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}">{{ __('stats::dashboard.tabs.'.$tab) }}</button>
@endforeach
</div>
@foreach ($tabs as $tab => $names)
<div role="tabpanel" class="stats-tabpanel" id="panel-{{ $tab }}" aria-labelledby="tab-{{ $tab }}" tabindex="-1">
<h2 class="stats-sr-only">{{ __('stats::dashboard.tabs.'.$tab) }}</h2>
@if ($tab === 'ai')
<div class="stats-kpis stats-primary" role="group" aria-label="{{ __('stats::dashboard.ai.summary') }}">
<div class="stats-kpi"><div class="stats-label">{{ __('stats::dashboard.ai.visits') }}</div><div class="stats-value">{{ Format::number($ai['visits']) }}</div><div class="stats-foot"><span class="stats-note">{{ __('stats::dashboard.ai.visits_note') }}</span></div></div>
<div class="stats-kpi"><div class="stats-label">{{ __('stats::dashboard.ai.visitors') }}</div><div class="stats-value">{{ Format::number($ai['visitors']) }}</div></div>
<div class="stats-kpi"><div class="stats-label">{{ __('stats::dashboard.ai.fetches') }}</div><div class="stats-value">{{ Format::number($ai['fetches']) }}</div><div class="stats-foot"><span class="stats-note">{{ __('stats::dashboard.ai.fetches_note') }}</span></div></div>
</div>
@include('stats::partials.chart', ['series' => $ai['series'], 'metrics' => Dashboard::AI_CHART_METRICS, 'title' => __('stats::dashboard.ai.chart'), 'id' => 'ai-chart'])
@endif
@php
    // Wide charts across the tab first; then the other charts and the reports alternately in a left and a right
    // column that each stack their cards (no row alignment, so no gaps under shorter cards). 'order' keeps the
    // original order when the columns collapse into one on narrow screens.
    $tabCharts = $charts[$tab] ?? [];
    $cards = [...array_filter($tabCharts, fn ($c) => ! $c['wide']), ...$names];
@endphp
@foreach (array_filter($tabCharts, fn ($c) => $c['wide']) as $chart)
@include('stats::partials.chart-card', ['chart' => $chart])
@endforeach
<div class="stats-columns">
@foreach ([0, 1] as $column)
<div class="stats-column">
@foreach ($cards as $i => $card)
@continue($i % 2 !== $column)
<div class="stats-cell" style="order:{{ $i }}">
@if (is_array($card))
@include('stats::partials.chart-card', ['chart' => $card])
@else
@include('stats::partials.report', ['name' => $card, 'rows' => $reports[$card], 'total' => $totals[$card]])
@endif
</div>
@endforeach
</div>
@endforeach
</div>
</div>
@endforeach
</section>
