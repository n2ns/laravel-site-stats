{{-- The description and the chart of Http\View\Charts::build() (inside a card or a Filament section). Needs: $chart. --}}
<p class="chart-desc">{{ $chart['description'] }}</p>
@if ($chart['empty'])
<p class="empty">{{ __('stats::dashboard.no_data') }}</p>
@else
@include('stats::partials.charts.'.$chart['type'], ['chart' => $chart])
@endif
