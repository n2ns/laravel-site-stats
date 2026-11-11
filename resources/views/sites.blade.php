<div class="page-head">
<h1>{{ __('stats::sites.title') }}</h1>
<a class="button" href="#new-site">{{ __('stats::sites.new_site') }}</a>
</div>

<div class="stack">
@if (session('stats_status'))
<div class="card notice" role="status">{{ session('stats_status') }}</div>
@endif

@if (isset($newKey))
<div class="card notice" role="status">
<p class="lead"><strong>{{ __('stats::sites.new_key_title') }}</strong>: {{ __('stats::sites.new_key', ['name' => $sites->firstWhere('public_id', $newKey['site'])?->name]) }}</p>
<div class="copy-box">
<pre>{{ $newKey['key'] }}</pre>
<button type="button" class="secondary" data-copy hidden>{{ __('stats::sites.copy') }}</button>
</div>
<p class="copy-status" role="status"></p>
</div>
@endif

@forelse ($sites as $site)
@php $editing = $errors->getBag('edit_'.$site->public_id)->any(); @endphp
<section class="card site-card" aria-labelledby="site-{{ $site->public_id }}">
<div class="card-head">
<h2 id="site-{{ $site->public_id }}">{{ $site->name }}</h2>
<a href="{{ route('stats.dashboard.site', ['publicId' => $site->public_id]) }}">{{ __('stats::sites.open_dashboard') }}</a>
</div>
<div class="meta">
<span>{{ implode(', ', $site->domains) }}</span>
<span class="badge">{{ $site->timezone }}</span>
@foreach ($site->locales ?? [] as $locale)<span class="badge">{{ $locale }}</span>@endforeach
@if ($site->public_counts)<span class="badge accent">{{ __('stats::sites.public_counts_on') }}</span>@endif
</div>

<h3>{{ __('stats::sites.embed_title') }}</h3>
<p class="help">{{ __('stats::sites.embed') }}</p>
<div class="copy-box">
<pre>&lt;script defer data-site="{{ $site->public_id }}" src="{{ $scriptUrl }}"&gt;&lt;/script&gt;</pre>
<button type="button" class="secondary" data-copy hidden>{{ __('stats::sites.copy') }}</button>
</div>
<p class="copy-status" role="status"></p>

<div class="section">
<h3>{{ __('stats::sites.key_title') }}</h3>
<form method="post" action="{{ route('stats.sites.key', ['publicId' => $site->public_id]) }}" class="key-row" @if ($site->server_key_hash) data-confirm="{{ __('stats::sites.rotate_confirm') }}" onsubmit="return confirm(this.dataset.confirm)" @endif>
@csrf
<p>{{ __('stats::sites.server_key', ['state' => $site->server_key_hash ? __('stats::sites.key_set') : __('stats::sites.key_not_set')]) }}</p>
<button type="submit" class="secondary">{{ $site->server_key_hash ? __('stats::sites.rotate_key') : __('stats::sites.generate_key') }}</button>
</form>
</div>

<details class="section" @if ($editing) open @endif>
<summary>{{ __('stats::sites.settings') }}</summary>
@include('stats::partials.site-form', ['site' => $site, 'bag' => 'edit_'.$site->public_id, 'action' => route('stats.sites.update', ['publicId' => $site->public_id]), 'method' => 'PUT', 'submit' => __('stats::sites.save')])
</details>
</section>
@empty
<div class="card"><p class="empty">{{ __('stats::sites.none') }}</p></div>
@endforelse

{{-- Folded unless there is no site yet or it came back with errors; the New site button opens it. --}}
<details class="card new-site" id="new-site" aria-labelledby="new-site-title"@if ($sites->isEmpty() || $errors->getBag('create')->any()) open @endif>
<summary class="card-head"><h2 id="new-site-title">{{ __('stats::sites.new_site') }}</h2></summary>
@include('stats::partials.site-form', ['site' => null, 'bag' => 'create', 'action' => route('stats.sites.store'), 'method' => 'POST', 'submit' => __('stats::sites.create')])
</details>
</div>
<script>
(function () {
  var newSite = document.getElementById('new-site');
  document.querySelectorAll('a[href="#new-site"]').forEach(function (link) {
    link.addEventListener('click', function () { newSite.open = true; });
  });

  var copied = @json(__('stats::sites.copied')), failed = @json(__('stats::sites.copy_failed'));
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    var source = button.closest('.copy-box').querySelector('pre');
    var status = button.closest('.copy-box').nextElementSibling;
    if (!source || !navigator.clipboard) return;
    button.hidden = false;
    button.addEventListener('click', function () {
      navigator.clipboard.writeText(source.textContent).then(function () {
        if (status) status.textContent = copied;
      }, function () {
        if (status) status.textContent = failed;
        var range = document.createRange();
        range.selectNodeContents(source);
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
      });
    });
  });
})();
</script>
