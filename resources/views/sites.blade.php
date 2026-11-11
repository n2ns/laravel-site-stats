@extends('stats::layout')
@section('title', __('stats::sites.title'))
@section('content')
@if (session('stats_status'))
<div class="panel notice" role="status">{{ session('stats_status') }}</div>
@endif

@if (isset($newKey))
<div class="panel notice" role="status">
<strong>{{ __('stats::sites.new_key_title') }}</strong>: {{ __('stats::sites.new_key', ['name' => $sites->firstWhere('public_id', $newKey['site'])?->name]) }}
<pre>{{ $newKey['key'] }}</pre>
</div>
@endif

<section class="panel">
<h1 style="margin-top:0;font-size:18px">{{ __('stats::sites.title') }}</h1>
@forelse ($sites as $site)
<details class="panel" @if ($errors->getBag('edit_'.$site->public_id)->any() || (isset($newKey) && $newKey['site'] === $site->public_id)) open @endif>
<summary>{{ $site->name }} <span class="muted">· {{ implode(', ', $site->domains) }} · {{ $site->timezone }}@if ($site->locales) · {{ implode(', ', $site->locales) }}@endif @if ($site->public_counts) · {{ __('stats::sites.public_counts_on') }}@endif</span></summary>
<p><a href="{{ route('stats.dashboard.site', ['publicId' => $site->public_id]) }}">{{ __('stats::sites.open_dashboard') }}</a></p>
<p class="muted" style="margin-bottom:4px">{{ __('stats::sites.embed') }}</p>
<pre>&lt;script defer data-site="{{ $site->public_id }}" src="{{ $scriptUrl }}"&gt;&lt;/script&gt;</pre>
@include('stats::partials.site-form', ['site' => $site, 'bag' => 'edit_'.$site->public_id, 'action' => route('stats.sites.update', ['publicId' => $site->public_id]), 'method' => 'PUT', 'submit' => __('stats::sites.save')])
<form method="post" action="{{ route('stats.sites.key', ['publicId' => $site->public_id]) }}" style="margin-top:12px" @if ($site->server_key_hash) data-confirm="{{ __('stats::sites.rotate_confirm') }}" onsubmit="return confirm(this.dataset.confirm)" @endif>
@csrf
<p class="muted">{{ __('stats::sites.server_key', ['state' => $site->server_key_hash ? __('stats::sites.key_set') : __('stats::sites.key_not_set')]) }}</p>
<button type="submit" class="secondary">{{ $site->server_key_hash ? __('stats::sites.rotate_key') : __('stats::sites.generate_key') }}</button>
</form>
</details>
@empty
<p class="muted">{{ __('stats::sites.none') }}</p>
@endforelse
</section>

<section class="panel">
<h2 style="margin-top:0;font-size:16px">{{ __('stats::sites.new_site') }}</h2>
@include('stats::partials.site-form', ['site' => null, 'bag' => 'create', 'action' => route('stats.sites.store'), 'method' => 'POST', 'submit' => __('stats::sites.create')])
</section>
@endsection
