@if (filled(config('stats.dashboard_back_url')))
<a class="stats-back" href="{{ url(config('stats.dashboard_back_url')) }}"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.5 15l-5-5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ config('stats.dashboard_back_label') ?? __('stats::dashboard.back') }}</a>
@endif
