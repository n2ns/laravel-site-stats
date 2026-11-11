{{-- Inline SVG donut of a report's main metric, with a text legend. Needs: $slices (Dashboard::donut()), $label. --}}
@php use N2ns\Stats\Http\View\Format; @endphp
<div class="donut-wrap">
<svg class="donut" viewBox="0 0 42 42" role="img" aria-label="{{ $label }}">
<circle class="track" cx="21" cy="21" r="15.9155"/>
@foreach ($slices as $slice)
@php $gap = count($slices) > 1 ? min(0.8, $slice['share'] * 50) : 0; @endphp
<circle class="{{ $slice['other'] ? 's-other' : 's'.$loop->index }}" cx="21" cy="21" r="15.9155" stroke-dasharray="{{ round($slice['share'] * 100 - $gap, 3) }} {{ round(100 - $slice['share'] * 100 + $gap, 3) }}" stroke-dashoffset="{{ round(25 - $slice['offset'] * 100, 3) }}"><title>{{ $slice['label'] }}: {{ Format::percent($slice['share']) }}</title></circle>
@endforeach
</svg>
<ul class="legend">
@foreach ($slices as $slice)
<li class="{{ $slice['other'] ? 's-other' : 's'.$loop->index }}"><span class="swatch" aria-hidden="true"></span><span class="name">{{ $slice['label'] }}</span> {{ Format::number($slice['value']) }} ({{ Format::percent($slice['share']) }})</li>
@endforeach
</ul>
</div>
