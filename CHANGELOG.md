# Changelog

All notable changes to N2N Stats are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Cookieless collection of page views, sources, entry and exit pages, outbound clicks, file
  downloads, custom events and unique visitors for many sites, stored only as daily aggregates.
- Visitors counted with a daily-salted hash of site, IP address and User-Agent; salts and hashes are
  deleted within two days.
- Visits split after 30 minutes of inactivity, with bounce rate and visit duration.
- Source classification (UTM, search, social, AI assistants, referral, direct, click ids) and
  country, device, browser, operating system and language reports.
- A dependency-free tracking script with automatic single-page-app page views, outbound and download
  tracking, custom events through `window.n2nStats()` or `data-stats-event`, and path exclusions.
- Server-side counting of AI fetchers with the `TrackAiFetches` middleware, also from other Laravel
  sites in client mode.
- A dashboard guarded by the `viewStats` gate, with period comparison, realtime visitors and CSV
  export, and an optional Filament plugin.
- A read-only API with scoped bearer tokens.
- Commands `stats:install`, `stats:site`, `stats:token` and `stats:maintain` (scheduled every five
  minutes); configurable retention of aggregates.
