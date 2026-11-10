<x-filament-widgets::widget>
<x-filament::section heading="Top sources · last 7 days">
@forelse ($sites as $site)
<div style="margin-bottom:1rem">
<a href="{{ $site['url'] }}" style="font-weight:600;text-decoration:underline">{{ $site['name'] }}</a>
@if ($site['sources'] === [])
<p style="opacity:.7">No visits in the last 7 days.</p>
@else
<table style="width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums">
<thead><tr><th scope="col" style="text-align:left">Source</th><th scope="col" style="text-align:left">Type</th><th scope="col" style="text-align:right">Visits</th><th scope="col" style="text-align:right">Visitors</th></tr></thead>
<tbody>
@foreach ($site['sources'] as $row)
<tr><td>{{ $row['source'] ?? '(direct)' }}</td><td>{{ $row['source_type'] }}</td><td style="text-align:right">{{ number_format((int) $row['visits']) }}</td><td style="text-align:right">{{ number_format((int) $row['visitors']) }}</td></tr>
@endforeach
</tbody>
</table>
@endif
</div>
@empty
<p>No sites yet.</p>
@endforelse
</x-filament::section>
</x-filament-widgets::widget>
