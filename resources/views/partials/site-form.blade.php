@php
    $errs = $errors->getBag($bag);
    $useOld = $errs->any();
    $id = $site?->public_id ?? 'new';
    $tz = $useOld ? old('timezone') : ($site?->timezone ?? 'UTC');
@endphp
<form method="post" action="{{ $action }}" class="site-form" novalidate>
@csrf
@if ($method !== 'POST') @method($method) @endif
@if ($errs->any())
<div class="error" role="alert"><ul>@foreach ($errs->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif
<label for="name-{{ $id }}">Name
<input id="name-{{ $id }}" name="name" maxlength="120" required value="{{ $useOld ? old('name') : $site?->name }}" @if ($errs->has('name')) aria-invalid="true" @endif>
</label>
<label for="domains-{{ $id }}">Domains, one per line (example.com also accepts www.example.com; *.example.com accepts every subdomain)
<textarea id="domains-{{ $id }}" name="domains" required @if ($errs->has('domains')) aria-invalid="true" @endif>{{ $useOld ? old('domains') : implode("\n", $site?->domains ?? []) }}</textarea>
</label>
<label for="timezone-{{ $id }}">Timezone (days are counted in this timezone)
<select id="timezone-{{ $id }}" name="timezone">
@foreach ($timezones as $zone)
<option value="{{ $zone }}" @selected($zone === $tz)>{{ $zone }}</option>
@endforeach
</select>
</label>
<div><button type="submit">{{ $submit }}</button></div>
</form>
