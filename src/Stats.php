<?php

namespace N2ns\Stats;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Tracking\ServerTracker;
use Throwable;

/**
 * Server-side counting helpers for the host app's controllers and views:
 * Stats::page() describes the current request's pageview (counted by the TrackPageviews middleware),
 * Stats::event() counts a custom event for the current request's visitor after the response has been sent,
 * Stats::script() (Blade: @statsScript) prints the tracking script tag for the app's own pages.
 */
final class Stats
{
    /** Request attribute that holds the details set with page(). */
    public const PAGE_ATTRIBUTE = 'stats.page';

    /**
     * The tracking script tag for the app's own pages: in host mode for the site of this app (Site::forHost) when it
     * owns the request host, in client mode for the configured site on the host. Empty when there is no such site or
     * the lookup fails (statistics never break a page). $attributes adds data-* attributes of the script, e.g.
     * ['data-pageviews' => 'off'] when page views are counted on the server.
     *
     * @param  array<string, string>  $attributes
     */
    public static function script(array $attributes = []): HtmlString
    {
        if (config('stats.mode') === 'client') {
            $site = (string) config('stats.client.site');
            $src = rtrim((string) config('stats.client.endpoint'), '/').'/js/stats.js';
            if ($site === '' || $src === '/js/stats.js') {
                return new HtmlString('');
            }
        } else {
            $host = request()->getHost();
            try {
                $model = Route::has('stats.script') ? Site::forHost($host) : null;
            } catch (Throwable $e) {
                Log::warning('N2N Stats: failed to find the site for the script tag', ['exception' => $e::class]);
                $model = null;
            }
            // The tag's URL follows the request host, so a pinned site gets a tag only on its own domains.
            if ($model === null || ! $model->allowsHost($host)) {
                return new HtmlString('');
            }
            [$site, $src] = [$model->public_id, route('stats.script')];
        }

        $extra = '';
        foreach ($attributes as $name => $value) {
            if (preg_match('/^data-[a-z][a-z-]*$/', $name) === 1) {
                $extra .= ' '.$name.'="'.e($value).'"';
            }
        }

        return new HtmlString('<script defer data-site="'.e($site).'"'.$extra.' src="'.e($src).'"></script>');
    }

    /** Title (cut to 200 characters), page type ([a-z0-9_-]{1,40}, otherwise ignored) and content group (cut to 120). */
    public static function page(?string $title = null, ?string $type = null, ?string $group = null): void
    {
        request()->attributes->set(self::PAGE_ATTRIBUTE, [
            'title' => ServerTracker::cut($title, 200),
            'page_type' => $type !== null && preg_match('/^[a-z0-9_-]{1,40}$/', $type) === 1 ? $type : null,
            'content_group' => ServerTracker::cut($group, 120),
        ]);
    }

    /** Name [a-z0-9_.-]{1,64} (otherwise nothing is counted), label cut to 120 characters, target an http(s) URL. */
    public static function event(string $name, ?string $label = null, ?string $target = null): void
    {
        $request = request();
        $fields = ['name' => $name, 'label' => ServerTracker::cut($label, 120), 'target' => $target];
        $done = false;
        // Terminating callbacks stay registered in long-running processes; each event is counted once.
        app()->terminating(function () use ($request, $fields, &$done): void {
            if (! $done) {
                $done = true;
                app(ServerTracker::class)->track($request, 'event', $fields);
            }
        });
    }
}
