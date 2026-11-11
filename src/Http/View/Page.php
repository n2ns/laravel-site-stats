<?php

namespace N2ns\Stats\Http\View;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

/**
 * A dashboard or site management page: standalone (stats::layout), or inside the host's admin layout when
 * config('stats.dashboard_layout') names one — a layout to extend ('view', the 'section' for the page and the
 * 'title_sections' for its title) or a layout component ('component', with the title as its title attribute).
 */
final class Page
{
    /** @param array<string, mixed> $data */
    public static function make(string $title, string $view, array $data): View
    {
        $body = new HtmlString(view($view, $data)->render());
        $layout = config('stats.dashboard_layout');

        return match (true) {
            is_array($layout) && isset($layout['view']) => view('stats::host-extends', compact('layout', 'title', 'body')),
            is_array($layout) && isset($layout['component']) => view('stats::host-component', compact('layout', 'title', 'body')),
            default => view('stats::layout', compact('title', 'body')),
        };
    }
}
