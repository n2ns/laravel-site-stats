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
<div class="field">
<label for="name-{{ $id }}">{{ __('stats::sites.labels.name') }}</label>
<input id="name-{{ $id }}" name="name" maxlength="120" required value="{{ $useOld ? old('name') : $site?->name }}" @if ($errs->has('name')) aria-invalid="true" @endif>
</div>
<div class="field">
<label for="timezone-{{ $id }}">{{ __('stats::sites.labels.timezone') }}</label>
<select id="timezone-{{ $id }}" name="timezone" aria-describedby="timezone-help-{{ $id }}">
@foreach ($timezones as $zone)
<option value="{{ $zone }}" @selected($zone === $tz)>{{ $zone }}</option>
@endforeach
</select>
<p class="help" id="timezone-help-{{ $id }}">{{ __('stats::sites.fields.timezone') }}</p>
</div>
<div class="field wide">
<label for="domains-{{ $id }}">{{ __('stats::sites.labels.domains') }}</label>
<textarea id="domains-{{ $id }}" name="domains" required aria-describedby="domains-help-{{ $id }}" @if ($errs->has('domains')) aria-invalid="true" @endif>{{ $useOld ? old('domains') : implode("\n", $site?->domains ?? []) }}</textarea>
<p class="help" id="domains-help-{{ $id }}">{{ __('stats::sites.fields.domains') }}</p>
</div>
<div class="field">
<label for="locales-{{ $id }}">{{ __('stats::sites.labels.locales') }}</label>
<textarea id="locales-{{ $id }}" name="locales" aria-describedby="locales-help-{{ $id }}" @if ($errs->has('locales')) aria-invalid="true" @endif>{{ $useOld ? old('locales') : implode("\n", $site?->locales ?? []) }}</textarea>
<p class="help" id="locales-help-{{ $id }}">{{ __('stats::sites.fields.locales') }}</p>
</div>
<div class="field">
<label for="default-locale-{{ $id }}">{{ __('stats::sites.labels.default_locale') }}</label>
<input id="default-locale-{{ $id }}" name="default_locale" maxlength="16" value="{{ $useOld ? old('default_locale') : $site?->default_locale }}" aria-describedby="default-locale-help-{{ $id }}" @if ($errs->has('default_locale')) aria-invalid="true" @endif>
<p class="help" id="default-locale-help-{{ $id }}">{{ __('stats::sites.fields.default_locale') }}</p>
</div>
<div class="field wide">
<label for="exclude-paths-{{ $id }}">{{ __('stats::sites.labels.exclude_paths') }}</label>
<textarea id="exclude-paths-{{ $id }}" name="exclude_paths" aria-describedby="exclude-paths-help-{{ $id }}" @if ($errs->has('exclude_paths')) aria-invalid="true" @endif>{{ $useOld ? old('exclude_paths') : implode("\n", $site?->exclude_paths ?? []) }}</textarea>
<p class="help" id="exclude-paths-help-{{ $id }}">{{ __('stats::sites.fields.exclude_paths') }}</p>
</div>
<div class="field wide">
<label for="public-counts-{{ $id }}" class="check">
<input type="checkbox" id="public-counts-{{ $id }}" name="public_counts" value="1" aria-describedby="public-counts-help-{{ $id }}" @checked($publicCounts)>
{{ __('stats::sites.fields.public_counts') }}
</label>
<p class="help" id="public-counts-help-{{ $id }}">{{ __('stats::sites.fields.public_counts_help') }}</p>
</div>
<div class="actions"><button type="submit">{{ $submit }}</button></div>
</form>
