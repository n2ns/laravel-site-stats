<x-filament-panels::page>
@include('stats::partials.styles')
<div class="stats-dash stats-filament">
@if ($site === null)
<div class="panel"><p>{{ __('stats::dashboard.no_sites') }} <a href="{{ route('stats.sites.index') }}">{{ __('stats::dashboard.manage_sites') }}</a></p></div>
@else
@include('stats::partials.dashboard')
@endif
<footer>{{ __('stats::dashboard.footer') }}</footer>
</div>
@include('stats::partials.scripts')
</x-filament-panels::page>
