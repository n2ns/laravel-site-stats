{{-- One chart of a report tab (Http\View\Charts::build()): title, description and the chart, or "no data". Needs: $chart. --}}
<article class="card chart-card{{ $chart['wide'] ? ' wide' : '' }}" id="chart-{{ $chart['name'] }}" aria-labelledby="chart-{{ $chart['name'] }}-title">
<div class="card-head"><h3 id="chart-{{ $chart['name'] }}-title">{{ $chart['title'] }}</h3></div>
@include('stats::partials.charts.body', ['chart' => $chart])
</article>
