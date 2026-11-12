<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title>{{ $title }} · {{ __('stats::dashboard.brand') }}</title>
@include('stats::partials.styles')
<style>
html,body{margin:0}
body.stats-dash{background:var(--bg);min-height:100vh}
.stats-dash .stats-appbar{background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--shadow)}
.stats-dash .stats-appbar-inner,.stats-dash .stats-wrap{max-width:1280px;margin:0 auto;padding:0 var(--space-5)}
.stats-dash .stats-appbar-inner{display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);min-height:64px}
.stats-dash .stats-brand{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:var(--fs-md);color:var(--text);text-decoration:none}
.stats-dash .stats-brand:hover{color:var(--text)}
.stats-dash .stats-brand svg{width:22px;height:22px;flex:none}
.stats-dash .stats-appbar-start{display:flex;align-items:center;gap:var(--space-4)}
.stats-dash nav.stats-main{display:flex;gap:var(--space-1)}
.stats-dash nav.stats-main a{color:var(--text-2);text-decoration:none;font-size:var(--fs-sm);font-weight:500;padding:6px 10px;border-radius:var(--radius-sm)}
.stats-dash nav.stats-main a:hover{background:var(--surface-2);color:var(--text)}
.stats-dash nav.stats-main a[aria-current=page]{color:var(--accent);background:var(--accent-soft)}
.stats-dash .stats-wrap{padding-top:var(--space-6);padding-bottom:var(--space-4)}
</style>
</head>
<body class="stats-dash">
<header class="stats-appbar">
<div class="stats-appbar-inner">
<div class="stats-appbar-start">
@include('stats::partials.back')
<a class="stats-brand" href="{{ route('stats.dashboard') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="var(--accent)"/><path d="M6 16l4-5 3 3 5-6" fill="none" stroke="var(--on-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('stats::dashboard.brand') }}</a>
</div>
@include('stats::partials.nav')
</div>
</header>
<div class="stats-wrap">
<main>
{{ $body }}
</main>
<footer>{{ __('stats::dashboard.footer') }}</footer>
</div>
</body>
</html>
