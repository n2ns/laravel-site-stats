{{-- A chart of the statistics page's report tabs (Widgets\Report\TabChart); the page includes partials.styles once. --}}
<x-filament-widgets::widget>
@if ($tabChart !== null)
<x-filament::section :heading="$tabChart['title']">
<div class="stats-dash stats-filament">
@include('stats::partials.charts.body', ['chart' => $tabChart])
</div>
</x-filament::section>
@endif
</x-filament-widgets::widget>
