<?php

namespace N2ns\Stats\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use N2ns\Stats\Stats;
use N2ns\Stats\Tracking\ServerTracker;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts page views on the server, after the response has been sent: successful (2xx) GET responses with an HTML
 * body, except prefetches, bots and the package's own dashboard pages. Title, page type and content group come from Stats::page(). A site counted
 * this way must not also send page views from the tracking script.
 */
class TrackPageviews
{
    public function __construct(private readonly ServerTracker $tracker) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();
        $type = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'))[0]));
        $purpose = strtolower($request->headers->get('Sec-Purpose', '').' '.$request->headers->get('Purpose', ''));
        if (! $request->isMethod('GET') || $status < 200 || $status >= 300 || $type !== 'text/html' || str_contains($purpose, 'prefetch')) {
            return;
        }
        // The package's own pages (dashboard, site manager) are not part of the site.
        if (str_starts_with((string) $request->route()?->getName(), 'stats.')) {
            return;
        }

        $referrer = $request->headers->get('Referer');
        $page = (array) $request->attributes->get(Stats::PAGE_ATTRIBUTE, []);
        $this->tracker->track($request, 'pageview', [
            'referrer' => is_string($referrer) && strlen($referrer) <= 2048 ? $referrer : null,
            'title' => $page['title'] ?? null,
            'page_type' => $page['page_type'] ?? null,
            'content_group' => $page['content_group'] ?? null,
        ]);
    }
}
