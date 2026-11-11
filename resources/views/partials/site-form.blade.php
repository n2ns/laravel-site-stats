@php
    $errs = $errors->getBag($bag);
    $useOld = $errs->any();
    $id = $site?->public_id ?? 'new';
    $tz = $useOld ? old('timezone') : ($site?->timezone ?? 'UTC');
    $publicCounts = $useOld ? (bool) old('public_counts') : (bool) $site?->public_counts;
@endphp
<form method="post" action="{{ $action }}" class="site-form" novalidate>
@csrf
@if ($method !== 'POST') @method($method) @endif
@if ($errs->any())
<div class="error" role="alert"><ul>@foreach ($errs->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif
<label for="name-{{ $id }}">{{ __('stats::sites.fields.name') }}
<input id="name-{{ $id }}" name="name" maxlength="120" required value="{{ $useOld ? old('name') : $site?->name }}" @if ($errs->has('name')) aria-invalid="true" @endif>
</label>
<label for="domains-{{ $id }}">{{ __('stats::sites.fields.domains') }}
<textarea id="domains-{{ $id }}" name="domains" required @if ($errs->has('domains')) aria-invalid="true" @endif>{{ $useOld ? old('domains') : implode("\n", $site?->domains ?? []) }}</textarea>
</label>
<label for="timezone-{{ $id }}">{{ __('stats::sites.fields.timezone') }}
<select id="timezone-{{ $id }}" name="timezone">
@foreach ($timezones as $zone)
<option value="{{ $zone }}" @selected($zone === $tz)>{{ $zone }}</option>
@endforeach
</select>
</label>
<label for="locales-{{ $id }}">{{ __('stats::sites.fields.locales') }}
<textarea id="locales-{{ $id }}" name="locales" @if ($errs->has('locales')) aria-invalid="true" @endif>{{ $useOld ? old('locales') : implode("\n", $site?->locales ?? []) }}</textarea>
</label>
<label for="default-locale-{{ $id }}">{{ __('stats::sites.fields.default_locale') }}
<input id="default-locale-{{ $id }}" name="default_locale" maxlength="16" value="{{ $useOld ? old('default_locale') : $site?->default_locale }}" @if ($errs->has('default_locale')) aria-invalid="true" @endif>
</label>
<label for="exclude-paths-{{ $id }}">{{ __('stats::sites.fields.exclude_paths') }}
<textarea id="exclude-paths-{{ $id }}" name="exclude_paths" @if ($errs->has('exclude_paths')) aria-invalid="true" @endif>{{ $useOld ? old('exclude_paths') : implode("\n", $site?->exclude_paths ?? []) }}</textarea>
</label>
<label for="public-counts-{{ $id }}" class="check">
<input type="checkbox" id="public-counts-{{ $id }}" name="public_counts" value="1" aria-describedby="public-counts-help-{{ $id }}" @checked($publicCounts)>
{{ __('stats::sites.fields.public_counts') }}
</label>
<p class="muted" id="public-counts-help-{{ $id }}" style="margin:0;font-size:12px">{{ __('stats::sites.fields.public_counts_help') }}</p>
<div><button type="submit">{{ $submit }}</button></div>
</form>
