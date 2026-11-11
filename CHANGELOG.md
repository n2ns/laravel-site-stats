# Changelog

All notable changes to N2N Stats are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.7] - 2026-11-12

### Added

- The dashboard and site pages can be shown inside the host's own Blade admin layout, with its menu around them:
  `dashboard_layout` names a layout to extend or a layout component.
- An optional back link at the top of the dashboard and site pages (`dashboard_back_url`, `dashboard_back_label`).

### Changed

- The site management page is titled Site settings, like the button that opens it.

## [0.1.6] - 2026-11-12

### Changed

- Sites are managed from a Site settings button at the top right of the statistics page, in the dashboard and in
  Filament; the Filament Sites resource is no longer a separate item of the panel navigation.

### Fixed

- `php artisan view:cache` (and `optimize`) no longer fails on apps without Filament: the package's Filament views
  are registered only where Filament is installed.

## [0.1.5] - 2026-11-11

### Changed

- The report tabs stand out: larger, semibold labels, the selected tab filled with the primary colour (the panel's
  inside Filament).

### Fixed

- Filament statistics page: the widgets of the report tabs load when their tab is shown, as intended. Before,
  every tab rendered with the page (slow to open), and the AI figures' sparklines, drawn while their tab was
  hidden, grew tall enough to cover part of the tab bar, so some tabs could no longer be clicked.
- The charts of the Filament statistics page no longer ask the server for new data every 5 seconds.
- `/index.php/pricing` (the front controller in the URL) counts as `/pricing`, and `/index.php` as `/`.

## [0.1.4] - 2026-11-11

### Added

- Charts in every report tab, in the dashboard and on the Filament page: time on page and scroll
  depth of all pages, content groups and page flow; daily visitors per source type and the bounce
  rate of the top sources; a world map of visitors per country; the daily count of the top custom
  events and the events per page view by page; AI fetchers. Browsers and operating systems also get
  a donut chart.
- The daily traffic chart shows the same number of days before: a dashed line in the dashboard, and
  lines hidden in the legend until clicked on the Filament page.

### Changed

- The dashboard looks like a default Filament panel (zinc grays, amber accent, Filament-style cards,
  tabs, tables and controls); inside a Filament panel the charts use the panel's primary colour.
- The cards of the statistics page (both) have a tinted title bar and a shadow.
- The cards of a report tab alternate between two columns that stack their cards, so shorter cards no
  longer leave gaps; on narrow screens they form one column in the same order. Wide tables of a card
  scroll sideways inside its column.

### Fixed

- Views per visit are the page views of the ended visits over the ended visits, so a day with page
  views from before counting began (such as the day imported history ends) no longer inflates them.
- Opening a site from the Filament Sites list (its name, View or Edit) showed 404: the list linked by the
  sequential id while the pages look sites up by public id. Sites are now addressed by public id everywhere.

## [0.1.3] - 2026-11-11

### Changed

- The package is renamed from `n2ns/laravel-stats` to `n2ns/laravel-site-stats`, so it is not confused
  with `spatie/laravel-stats` and `wnx/laravel-stats`. Namespaces, config, tables and routes are unchanged:
  `composer remove n2ns/laravel-stats && composer require n2ns/laravel-site-stats`.
- Reports of visits (source types, sources, campaigns, landing pages, AI referrals, countries, devices,
  browsers, operating systems and languages) are sorted and shared by visitors instead of visits, so
  imported days without visit data are ranked and shared correctly.
- Links that AI assistants tag with only `utm_source=<their host>` (such as ChatGPT's
  `utm_source=chatgpt.com`) count as AI referrals instead of UTM campaigns. `php artisan migrate`
  moves the sources and landing pages already counted, and the open visits, to AI.

### Fixed

- The entry pages report shows the visitors who entered on each page, instead of all visitors of the
  page. Adds `entry_visitors` to `stats_daily_pages` (run `php artisan migrate`); earlier days are
  filled from the landing pages.
- The bounce rate of a source without visits (such as imported history) shows – instead of 0% (`null`
  in the API).
- Views per visit in the summary leaves out days that recorded page views but no visits (such as
  imported history), instead of dividing their page views by the visits of other days.

## [0.1.2] - 2026-11-11

### Added

- Single-site setup on equal terms with the multi-site one: `stats:install --site= --domain=` creates
  the app's own site, the `@statsScript` Blade directive prints its script tag (also in client mode,
  pointing to the host), and the dashboards hide the site switcher when there is one site.

### Changed

- When several sites' domains match the request host (and `host_site` is not set), server-side
  counting uses the oldest of them.

## [0.1.1] - 2026-11-11

### Added

- Time on page and scroll depth: the script reports them when a page is hidden or left; the
  `engagement` report shows the average time and the share of page views reaching 10 s, 30 s, 1 min,
  3 min and 25%, 50%, 75%, 100% scroll per page, and the summary has the average time on page.
  Visit duration now includes the time on the last page. Adds columns to `stats_daily_pages`
  (run `php artisan migrate`).
- Filament pages rebuilt from native Filament components: a statistics page with filters, KPI cards,
  charts and report tables (tabs load when opened), a Sites resource (create, edit, embed code,
  server key, delete) and the dashboard widgets. Filament 5 only.
- Redesigned standalone dashboard: light and dark themes, primary and secondary KPIs with
  sparklines, a daily chart with a metric selector and tooltips, report cards with top 10, show all
  and an "All columns" view, a cleaner Sites page, phone layout.
- The daily series (`timeseries`) also has pages viewed, views per visit, bounce rate, average visit
  duration and average time on page.
- `dashboard_enabled` config option to turn off the standalone dashboard and site management pages
  when the statistics are shown in the host's own admin.
- `event_locales` report: outbound clicks, downloads and custom events per site language version of
  the page they happened on, on the dashboard's Events tab and in the API.

### Changed

- Server keys created on the dashboard have the same format as those from `stats:site key`; the site
  settings rules are shared by the dashboard, the Filament resource and the command, so excluded
  paths with control characters are now rejected everywhere.

## [0.1.0] - 2026-11-11

### Added

- Cookieless collection of page views, sources, entry and exit pages, landing pages per source,
  moves between pages, outbound clicks, file downloads, custom events and unique visitors for many
  sites, stored only as daily aggregates.
- Page titles, page types and content groups reported by the page, and per-site language versions
  taken from the URL prefix.
- Outbound clicks, downloads and custom events credited to the source and entry page of their visit.
- Visitors counted with a daily-salted hash of site, IP address and User-Agent; salts and hashes are
  deleted within two days.
- Visits split after 30 minutes of inactivity, with bounce rate and visit duration.
- Source classification (UTM, search, social, AI assistants, referral, direct, click ids) and
  country, device, browser, operating system and language reports.
- A dependency-free tracking script with automatic single-page-app page views, outbound and download
  tracking with labels, custom events with label and target through `window.n2nStats()` or
  `data-stats-event`, page meta tags, path exclusions, and `data-pageviews="off"` for sites that
  count page views on the server.
- Server-side counting of AI fetchers (`TrackAiFetches`), of page views (`TrackPageviews`) and of
  events (`Stats::event()`), also from other Laravel sites in client mode; per-site excluded paths.
- A dashboard in English and Simplified Chinese guarded by the `viewStats` gate, with complete-day
  period comparison, realtime visitors, shares, donut charts, sortable tables and CSV export, and an
  optional Filament plugin with the full dashboard page and widgets.
- A read-only API with scoped bearer tokens, taking a date range or one of the dashboard's preset
  periods, with paging, a snapshot of all reports and AI figures.
- An optional public counts endpoint for showing all-time view and event counts on the site.
- Commands `stats:install`, `stats:site`, `stats:token` and `stats:maintain` (scheduled every five
  minutes); configurable retention of aggregates.

[0.1.7]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.7
[0.1.6]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.6
[0.1.5]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.5
[0.1.4]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.4
[0.1.3]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.3
[0.1.2]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.2
[0.1.1]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.1
[0.1.0]: https://github.com/n2ns/laravel-site-stats/releases/tag/v0.1.0
