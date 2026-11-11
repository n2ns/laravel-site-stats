<?php

namespace N2ns\Stats\Http\Controllers;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use N2ns\Stats\Models\Site;

class SiteController
{
    private const MAX_DOMAINS = 50;

    private const MAX_LOCALES = 20;

    private const MAX_EXCLUDE_PATHS = 50;

    private const MAX_PATH_LENGTH = 200;

    private const LOCALE = '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/';

    private const HOSTNAME = '/^(\*\.)?(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    public function index(): View
    {
        return $this->page();
    }

    public function store(Request $request): RedirectResponse
    {
        $site = Site::create($this->validated($request, 'create'));

        return redirect()->route('stats.sites.index')->with('stats_status', __('stats::sites.created', ['name' => $site->name]));
    }

    public function update(Request $request, string $publicId): RedirectResponse
    {
        $site = $this->site($publicId);
        $site->update($this->validated($request, 'edit_'.$site->public_id));

        return redirect()->route('stats.sites.index')->with('stats_status', __('stats::sites.saved', ['name' => $site->name]));
    }

    /** Generates a new client-mode server key; the plain key is shown in this response only and never stored. */
    public function rotateKey(string $publicId): Response
    {
        $site = $this->site($publicId);
        $key = bin2hex(random_bytes(24));
        $site->forceFill(['server_key_hash' => hash('sha256', $key)])->save();

        return response($this->page(['newKey' => ['site' => $site->public_id, 'key' => $key]]))
            ->header('Cache-Control', 'no-store');
    }

    /** @param array<string, mixed> $extra */
    private function page(array $extra = []): View
    {
        return view('stats::sites', $extra + [
            'sites' => Site::query()->orderBy('name')->orderBy('id')->get(),
            'timezones' => timezone_identifiers_list(),
            'scriptUrl' => route('stats.script'),
        ]);
    }

    private function site(string $publicId): Site
    {
        return Site::query()->where('public_id', $publicId)->firstOrFail();
    }

    /**
     * @return array{name: string, domains: list<string>, timezone: string, locales: list<string>, default_locale: string|null, exclude_paths: list<string>, public_counts: bool}
     */
    private function validated(Request $request, string $errorBag): array
    {
        $message = fn (string $key, array $replace = []): string => (string) __('stats::sites.validation.'.$key, $replace);
        $data = $request->validateWithBag($errorBag, [
            'name' => ['required', 'string', 'max:120'],
            'domains' => ['required', 'string', 'max:10000', function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                $domains = self::lines((string) $value);
                if ($domains === []) {
                    $fail($message('domains_empty'));
                } elseif (count($domains) > self::MAX_DOMAINS) {
                    $fail($message('domains_max', ['max' => self::MAX_DOMAINS]));
                }
                foreach ($domains as $domain) {
                    if (preg_match(self::HOSTNAME, $domain) !== 1) {
                        $fail($message('domain_invalid', ['value' => $domain]));
                    }
                }
            }],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locales' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                $locales = self::lines((string) $value);
                if (count($locales) > self::MAX_LOCALES) {
                    $fail($message('locales_max', ['max' => self::MAX_LOCALES]));
                }
                foreach ($locales as $locale) {
                    if (preg_match(self::LOCALE, $locale) !== 1) {
                        $fail($message('locale_invalid', ['value' => $locale]));
                    }
                }
            }],
            'default_locale' => ['nullable', 'string', 'max:16', function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                $locale = strtolower(trim((string) $value));
                if (preg_match(self::LOCALE, $locale) !== 1) {
                    $fail($message('default_locale_invalid', ['value' => $locale]));
                }
            }],
            'exclude_paths' => ['nullable', 'string', 'max:20000', function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                $paths = self::lines((string) $value, lower: false);
                if (count($paths) > self::MAX_EXCLUDE_PATHS) {
                    $fail($message('paths_max', ['max' => self::MAX_EXCLUDE_PATHS]));
                }
                foreach ($paths as $path) {
                    if (! str_starts_with($path, '/') || mb_strlen($path) > self::MAX_PATH_LENGTH) {
                        $fail($message('path_invalid', ['value' => $path, 'max' => self::MAX_PATH_LENGTH]));
                    }
                }
            }],
        ], [
            'required' => $message('required'),
            'string' => $message('string'),
            'max' => $message('max'),
            'timezone' => $message('timezone'),
        ], [
            'name' => __('stats::sites.attributes.name'),
            'domains' => __('stats::sites.attributes.domains'),
            'timezone' => __('stats::sites.attributes.timezone'),
            'locales' => __('stats::sites.attributes.locales'),
            'default_locale' => __('stats::sites.attributes.default_locale'),
            'exclude_paths' => __('stats::sites.attributes.exclude_paths'),
        ]);

        $defaultLocale = strtolower(trim((string) ($data['default_locale'] ?? '')));

        return [
            'name' => trim((string) $data['name']),
            'domains' => self::lines((string) $data['domains']),
            'timezone' => (string) $data['timezone'],
            'locales' => self::lines((string) ($data['locales'] ?? '')),
            'default_locale' => $defaultLocale !== '' ? $defaultLocale : null,
            'exclude_paths' => self::lines((string) ($data['exclude_paths'] ?? ''), lower: false),
            'public_counts' => $request->boolean('public_counts'),
        ];
    }

    /** @return list<string> one entry per line, trimmed (and lower-cased unless $lower is false), without blanks or duplicates */
    private static function lines(string $text, bool $lower = true): array
    {
        $lines = preg_split('/\R/', $lower ? strtolower($text) : $text) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== '')));
    }
}
