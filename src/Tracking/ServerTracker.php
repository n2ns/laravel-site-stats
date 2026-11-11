<?php

namespace N2ns\Stats\Tracking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use N2ns\Stats\Collector\Client;
use N2ns\Stats\Collector\Collector;
use N2ns\Stats\Collector\Payload;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\BotDetector;
use Throwable;

/**
 * Counts a pageview or custom event of the current request on the server (TrackPageviews, Stats::event). Host mode
 * records it through the Collector; client mode POSTs it to the host's /api/server-event with the site key.
 */
class ServerTracker
{
    /** Query parameters kept in the page URL, the same as the tracking script keeps. */
    private const KEEP_PARAMETERS = '/^(utm_[a-z]+|fbclid|gclid|gbraid|wbraid|dclid|msclkid|ttclid|li_fat_id|twclid)$/';

    private const ANY_SITE = '00000000000000000000000000';

    public function __construct(private readonly Collector $collector, private readonly BotDetector $bots) {}

    /**
     * @param  'pageview'|'event'  $kind
     * @param  array<string, string|null>  $fields  pageview: referrer, title, page_type, content_group; event: name, label, target
     */
    public function track(Request $request, string $kind, array $fields): void
    {
        try {
            $userAgent = (string) $request->userAgent();
            if ($this->bots->isBot($userAgent)) {
                return;
            }
            $fields = ['url' => self::pageUrl($request)] + $fields;
            // The same checks the host applies, so client mode never sends what the host would reject.
            if (Payload::fromArray(self::payloadData(self::ANY_SITE, $kind, $fields)) === null) {
                return;
            }

            match (config('stats.mode')) {
                'host' => $this->record($request, $kind, $fields, $userAgent),
                'client' => $this->send($request, $kind, $fields, $userAgent),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('N2N Stats: failed to count a server-side '.$kind, ['exception' => $e::class]);
        }
    }

    /** The request URL with only the attribution query parameters (UTM, click ids). */
    public static function pageUrl(Request $request): string
    {
        $kept = array_filter(explode('&', (string) $request->server('QUERY_STRING')), fn (string $pair): bool => $pair !== ''
            && preg_match(self::KEEP_PARAMETERS, strtolower(urldecode(explode('=', $pair, 2)[0]))) === 1);

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().($kept === [] ? '' : '?'.implode('&', $kept));
    }

    /**
     * Payload fields (Payload::fromArray keys) of a server-side pageview or event.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function payloadData(string $site, string $kind, array $fields): array
    {
        $data = ['s' => $site, 't' => $kind, 'u' => $fields['url'] ?? null];

        return $kind === 'pageview'
            ? $data + ['r' => $fields['referrer'] ?? null, 'pt' => $fields['title'] ?? null, 'py' => $fields['page_type'] ?? null, 'pg' => $fields['content_group'] ?? null]
            : $data + ['n' => $fields['name'] ?? null, 'l' => $fields['label'] ?? null, 'g' => $fields['target'] ?? null];
    }

    /** @param  array<string, string|null>  $fields */
    private function record(Request $request, string $kind, array $fields, string $userAgent): void
    {
        // A pinned site does not depend on the Host header (see TrackAiFetches).
        $pinned = config('stats.host_site');
        $site = is_string($pinned) && $pinned !== ''
            ? Site::query()->where('public_id', $pinned)->first()
            : Site::query()->get()->first(fn (Site $site): bool => $site->allowsHost($request->getHost()));
        $payload = $site === null ? null : Payload::fromArray(self::payloadData($site->public_id, $kind, $fields));
        if ($payload === null) {
            return;
        }

        $client = new Client((string) $request->ip(), $userAgent, $request->headers->get('CF-IPCountry'), $request->headers->get('Accept-Language'));
        $this->collector->collectFrom($payload, $client);
    }

    /** @param  array<string, string|null>  $fields */
    private function send(Request $request, string $kind, array $fields, string $userAgent): void
    {
        $endpoint = (string) config('stats.client.endpoint');
        $site = (string) config('stats.client.site');
        $key = (string) config('stats.client.key');
        if ($endpoint === '' || $site === '' || $key === '') {
            return;
        }

        $timeout = (int) config('stats.client.timeout_seconds', 2);
        Http::timeout($timeout)
            ->connectTimeout($timeout)
            ->withToken($key)
            ->acceptJson()
            ->post(rtrim($endpoint, '/').'/api/server-event', ['site' => $site, 'kind' => $kind] + $fields + [
                'ip' => (string) $request->ip(),
                'user_agent' => mb_substr($userAgent, 0, 512),
                'country' => self::cut($request->headers->get('CF-IPCountry'), 16),
                'accept_language' => self::cut($request->headers->get('Accept-Language'), 256),
            ]);
    }

    /** $value cut to $max characters; null when empty. */
    public static function cut(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', mb_scrub($value, 'UTF-8')));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
