{{-- World map of visitors per country (Http\View\WorldMap): darker is more visitors; hover shows the count. Needs: $chart with map. --}}
@php
    use N2ns\Stats\Http\View\Format;
    $m = $chart['map'];
    $tip = fn (string $code, int $visitors): string => \N2ns\Stats\Http\View\Dashboard::text('countries', 'value', $code).': '.__('stats::dashboard.charts.world_map.visitors', ['count' => Format::number($visitors)]);
@endphp
<div class="world">
<svg viewBox="0 0 {{ $m['width'] }} {{ $m['height'] }}" role="img" aria-label="{{ $chart['title'] }}">
@foreach ($m['shapes'] as $s)
<path class="c{{ $s['level'] }}" d="{{ $s['d'] }}"><title>{{ $tip($s['code'], $s['visitors']) }}</title></path>
@endforeach
@foreach ($m['points'] as $p)
<circle class="c{{ $p['level'] }}" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="6"><title>{{ $tip($p['code'], $p['visitors']) }}</title></circle>
@endforeach
</svg>
</div>
<div class="world-legend" aria-hidden="true"><span>{{ __('stats::dashboard.charts.world_map.fewer') }}</span>@for ($i = 1; $i <= 5; $i++)<span class="swatch c{{ $i }}"></span>@endfor<span>{{ __('stats::dashboard.charts.world_map.more') }}</span></div>
@if ($m['unmapped'] > 0)
<p class="chart-desc">{{ __('stats::dashboard.charts.world_map.unmapped', ['count' => Format::number($m['unmapped'])]) }}</p>
@endif
