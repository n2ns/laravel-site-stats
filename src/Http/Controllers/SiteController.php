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

    private const HOSTNAME = '/^(\*\.)?(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    public function index(): View
    {
        return $this->page();
    }

    public function store(Request $request): RedirectResponse
    {
        $site = Site::create($this->validated($request, 'create'));

        return redirect()->route('stats.sites.index')->with('stats_status', "Site “{$site->name}” created. Add the embed code below to its pages.");
    }

    public function update(Request $request, string $publicId): RedirectResponse
    {
        $site = $this->site($publicId);
        $site->update($this->validated($request, 'edit_'.$site->public_id));

        return redirect()->route('stats.sites.index')->with('stats_status', "Site “{$site->name}” saved.");
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

    /** @return array{name: string, domains: list<string>, timezone: string} */
    private function validated(Request $request, string $errorBag): array
    {
        $data = $request->validateWithBag($errorBag, [
            'name' => ['required', 'string', 'max:120'],
            'domains' => ['required', 'string', 'max:10000', function (string $attribute, mixed $value, Closure $fail): void {
                $domains = self::domains((string) $value);
                if ($domains === []) {
                    $fail('Enter at least one domain.');
                } elseif (count($domains) > self::MAX_DOMAINS) {
                    $fail('Enter at most '.self::MAX_DOMAINS.' domains.');
                }
                foreach ($domains as $domain) {
                    if (preg_match(self::HOSTNAME, $domain) !== 1) {
                        $fail("“{$domain}” is not a hostname (example.com) or wildcard (*.example.com).");
                    }
                }
            }],
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        return [
            'name' => trim((string) $data['name']),
            'domains' => self::domains((string) $data['domains']),
            'timezone' => (string) $data['timezone'],
        ];
    }

    /** @return list<string> one hostname per line, lower-cased, without blanks or duplicates */
    private static function domains(string $text): array
    {
        $lines = preg_split('/\R/', strtolower($text)) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== '')));
    }
}
