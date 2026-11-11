<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title>@yield('title') · {{ __('stats::dashboard.brand') }}</title>
<style>
body{margin:0;background:#f6f7f9}
@media (prefers-color-scheme:dark){body{background:#0f1216}}
.wrap{max-width:1200px;margin:0 auto;padding:16px}
header.top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
header.top .brand{font-weight:700;font-size:16px;text-decoration:none;color:var(--text)}
nav.main a{margin-left:12px}
</style>
@include('stats::partials.styles')
</head>
<body>
<div class="wrap stats-dash">
<header class="top">
<a class="brand" href="{{ route('stats.dashboard') }}">{{ __('stats::dashboard.brand') }}</a>
<nav class="main" aria-label="{{ __('stats::dashboard.nav.main') }}"><a href="{{ route('stats.dashboard') }}">{{ __('stats::dashboard.nav.dashboard') }}</a><a href="{{ route('stats.sites.index') }}">{{ __('stats::dashboard.nav.sites') }}</a></nav>
</header>
<main>
@yield('content')
</main>
<footer>{{ __('stats::dashboard.footer') }}</footer>
</div>
@stack('scripts')
</body>
</html>
