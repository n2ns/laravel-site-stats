{{-- A dashboard or site page inside the host's admin layout (Page, config dashboard_layout). --}}
@include('stats::partials.styles')
<div class="stats-dash stats-embedded">
<div class="stats-topbar">
@include('stats::partials.back')
@include('stats::partials.nav')
</div>
{{ $body }}
<footer>{{ __('stats::dashboard.footer') }}</footer>
</div>
