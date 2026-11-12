{{-- Horizontal bars: a label (and a smaller line under it), the bar and its value. Needs: $chart with rows. --}}
<ul class="stats-hbars" aria-label="{{ $chart['title'] }}">
@foreach ($chart['rows'] as $row)
<li>
<div class="stats-hbar-label"><span class="stats-lbl" title="{{ $row['label'] }}">{{ $row['label'] }}</span>@if ($row['sub'] !== null)<span class="stats-sub">{{ $row['sub'] }}</span>@endif</div>
<div class="stats-hbar-track" aria-hidden="true"><span class="stats-hbar-fill" style="width:{{ min(100, $row['width']) }}%"></span></div>
<div class="stats-hbar-value">{{ $row['value'] }}</div>
</li>
@endforeach
</ul>
