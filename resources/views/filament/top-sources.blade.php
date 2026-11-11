@php use N2ns\Stats\Http\View\Dashboard; @endphp
<x-filament-widgets::widget>
<x-filament::section :heading="__('stats::filament.top_sources')">
@forelse ($sites as $site)
<div style="margin-bottom:1rem">
<a href="{{ $site['url'] }}" style="font-weight:600;text-decoration:underline">{{ $site['name'] }}</a>
@if ($site['sources'] === [])
<p style="opacity:.7">{{ __('stats::filament.no_visits') }}</p>
@else
<table style="width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums">
<thead><tr><th scope="col" style="text-align:left">{{ __('stats::reports.columns.source') }}</th><th scope="col" style="text-align:left">{{ __('stats::reports.columns.source_type') }}</th><th scope="col" style="text-align:right">{{ __('stats::reports.columns.visits') }}</th><th scope="col" style="text-align:right">{{ __('stats::reports.columns.visitors') }}</th></tr></thead>
<tbody>
@foreach ($site['sources'] as $row)
<tr><td>{{ $row['source'] ?? __('stats::filament.direct') }}</td><td>{{ Dashboard::text('sources', 'source_type', $row['source_type']) }}</td><td style="text-align:right">{{ number_format((int) $row['visits']) }}</td><td style="text-align:right">{{ number_format((int) $row['visitors']) }}</td></tr>
@endforeach
</tbody>
</table>
@endif
</div>
@empty
<p>{{ __('stats::filament.no_sites') }}</p>
@endforelse
</x-filament::section>
</x-filament-widgets::widget>
