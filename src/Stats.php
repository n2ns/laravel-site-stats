<?php

namespace N2ns\Stats;

use N2ns\Stats\Tracking\ServerTracker;

/**
 * Server-side counting helpers for the host app's controllers and views:
 * Stats::page() describes the current request's pageview (counted by the TrackPageviews middleware),
 * Stats::event() counts a custom event for the current request's visitor after the response has been sent.
 */
final class Stats
{
    /** Request attribute that holds the details set with page(). */
    public const PAGE_ATTRIBUTE = 'stats.page';

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
