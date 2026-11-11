{{-- Horizontal bars: a label (and a smaller line under it), the bar and its value. Needs: $chart with rows. --}}
<ul class="hbars" aria-label="{{ $chart['title'] }}">
@foreach ($chart['rows'] as $row)
<li>
<div class="hbar-label"><span class="lbl" title="{{ $row['label'] }}">{{ $row['label'] }}</span>@if ($row['sub'] !== null)<span class="sub">{{ $row['sub'] }}</span>@endif</div>
<div class="hbar-track" aria-hidden="true"><span class="hbar-fill" style="width:{{ min(100, $row['width']) }}%"></span></div>
<div class="hbar-value">{{ $row['value'] }}</div>
</li>
@endforeach
</ul>
