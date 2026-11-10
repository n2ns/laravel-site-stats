@extends('stats::layout')
@section('title', 'Sites')
@section('content')
@if (session('stats_status'))
<div class="panel notice" role="status">{{ session('stats_status') }}</div>
@endif

@if (isset($newKey))
<div class="panel notice" role="status">
<strong>New server key</strong> for {{ $sites->firstWhere('public_id', $newKey['site'])?->name }}. Copy it now: it is shown only once and the previous key no longer works.
<pre>{{ $newKey['key'] }}</pre>
</div>
@endif

<section class="panel">
<h1 style="margin-top:0;font-size:18px">Sites</h1>
@forelse ($sites as $site)
<details class="panel" @if ($errors->getBag('edit_'.$site->public_id)->any() || (isset($newKey) && $newKey['site'] === $site->public_id)) open @endif>
<summary>{{ $site->name }} <span class="muted">· {{ implode(', ', $site->domains) }} · {{ $site->timezone }}</span></summary>
<p><a href="{{ route('stats.dashboard.site', ['publicId' => $site->public_id]) }}">Open dashboard</a></p>
<p class="muted" style="margin-bottom:4px">Embed code (add to every page, e.g. before &lt;/head&gt;):</p>
<pre>&lt;script defer data-site="{{ $site->public_id }}" src="{{ $scriptUrl }}"&gt;&lt;/script&gt;</pre>
@include('stats::partials.site-form', ['site' => $site, 'bag' => 'edit_'.$site->public_id, 'action' => route('stats.sites.update', ['publicId' => $site->public_id]), 'method' => 'PUT', 'submit' => 'Save'])
<form method="post" action="{{ route('stats.sites.key', ['publicId' => $site->public_id]) }}" style="margin-top:12px">
@csrf
<p class="muted">Server key (client mode, for AI fetch events sent from another Laravel app): {{ $site->server_key_hash ? 'set' : 'not set' }}.</p>
<button type="submit" class="secondary" onclick="return {{ $site->server_key_hash ? "confirm('Replace the current server key? The old key stops working.')" : 'true' }}">{{ $site->server_key_hash ? 'Rotate server key' : 'Generate server key' }}</button>
</form>
</details>
@empty
<p class="muted">No sites yet. Create the first one below.</p>
@endforelse
</section>

<section class="panel">
<h2 style="margin-top:0;font-size:16px">New site</h2>
@include('stats::partials.site-form', ['site' => null, 'bag' => 'create', 'action' => route('stats.sites.store'), 'method' => 'POST', 'submit' => 'Create site'])
</section>
@endsection
