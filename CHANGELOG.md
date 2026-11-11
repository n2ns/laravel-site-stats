# Changelog

All notable changes to N2N Stats are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[0.1.1]: https://github.com/n2ns/laravel-stats/releases/tag/v0.1.1
[0.1.0]: https://github.com/n2ns/laravel-stats/releases/tag/v0.1.0
