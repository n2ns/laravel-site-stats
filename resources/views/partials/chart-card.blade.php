{{-- One chart of a report tab (Http\View\Charts::build()): title, description and the chart, or "no data". Needs: $chart. --}}
<article class="stats-card stats-chart-card{{ $chart['wide'] ? ' stats-wide' : '' }}" id="chart-{{ $chart['name'] }}" aria-labelledby="chart-{{ $chart['name'] }}-title">
<div class="stats-card-head"><h3 id="chart-{{ $chart['name'] }}-title">{{ $chart['title'] }}</h3></div>
@include('stats::partials.charts.body', ['chart' => $chart])
</article>
