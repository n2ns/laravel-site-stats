# N2N Stats (n2ns/laravel-site-stats) design

N2N Stats is an open-source, cookieless web analytics package for Laravel, for a single site or for many: installed in a site's own Laravel app it counts that site in its own database and admin; one installation can also count any number of other websites. Only daily aggregates are kept. This document records how the package works and why. Where it and the code disagree, the code is right.

## 1. Scope

| Topic | Design |
| --- | --- |
| Form | Laravel package `n2ns/laravel-site-stats`, product name N2N Stats, Apache-2.0 |
| Data | Page views (with page title, page type and content group), sources, entry and exit pages, landing pages per source, page-to-page moves, outbound clicks, downloads, custom events (name, label, target), AI referrals and AI fetches, unique visitors, the site language version of each page. Conversions, revenue and funnels are not part of this version |
| Unique visitors | No cookies: `sha256(daily salt + site public id + IP + User-Agent)`. The salt is random per day and deleted within 48 hours after the day ends |
| User interface | A built-in dashboard in English and Simplified Chinese (access decided by the host app's `viewStats` gate) that can be turned off, optional Filament pages built from native Filament components (statistics page, Sites resource, widgets), a read-only JSON API, an optional public counts endpoint |
| Abuse resistance | Site id, domain and origin checks, rate limits, field validation, bot filters, a cap on new daily rows. The dashboard and the docs state that the numbers are for reference only |
| Retention | Daily aggregates are kept forever by default (configurable in days); salts and deduplication data are deleted within 48 hours after the day ends; a visit in progress ends after 30 minutes without activity and is then deleted |
| Out of scope | Cookie consent flows (not needed), any geolocation that sends IP addresses to a third party |

## 2. Techniques

- A page view is counted when the browser reports it (the script) or, on sites that choose it, by middleware on the server after a successful HTML response; bots and AI fetchers are recognised by User-Agent lists.
- Sources are classified as UTM, search, social, AI referral, other referral or direct, with click ids as a fallback.
- Only daily aggregates are stored, plus a per-day deduplication table; no table of individual page views.
- Counter writes insert the deduplication row first and then increment; if the counter write fails, the deduplication row is removed again.
- Outbound clicks include the middle mouse button.
- Strict field validation and length limits on everything a browser sends.
- Visits are split after 30 minutes of inactivity, which gives bounce rate and visit duration.
- File downloads are detected automatically; ranges are compared with the previous period over complete days; visitors online now; device, browser, OS, country, browser language and site language dimensions.
- Events are credited to the source and the entry page of the visit they happen in, so outbound clicks and events can be compared per source, campaign and landing page.
- Pages describe themselves (title, type, content group) so reports can show titles and group the language versions of one piece of content.
- One service for many sites with a one-line script; custom events; a token-protected read API; single-page-app navigation is counted automatically.
- Elements marked with `data-` attributes send events without any JavaScript.
- An install command, a configurable retention period and automatic cleanup.
- The metric definitions are documented (README "How the metrics are defined").

## 3. Architecture

```
Site pages  <script defer data-site="<public id>" src="https://<collector>/js/stats.js">
   │  POST https://<collector>/api/event   (text/plain JSON, sendBeacon, no CORS preflight)
   ▼
Collector ── site and domain checks → rate limits → bot filter → visitor hash
   │          → visit tracking → daily aggregate tables
   ▼
Host database (daily aggregates + temporary tables)
   ├── Dashboard   https://<host app>/<dashboard_path>        (gate viewStats)
   ├── Filament statistics page, Sites resource and widgets (optional)
   ├── Read API    https://<collector>/api/v1/...             (Bearer token)
   └── Public counts https://<collector>/api/public/...       (per site, off by default)

Other Laravel sites can install the same package in client mode:
   middleware counts AI fetches, and optionally page views and server-side events
   → POST https://<collector>/api/server-event (site server key)
```

- Two setups, equally supported and combinable in one installation:
  - **Single site**: the package is installed in the site's own Laravel app; the script and collector are served on the app's own domain (`@statsScript` prints the tag of the app's site, found by `host_site` or by the request host), server-side counting writes to the local database, and the statistics are in the app's own dashboard or Filament admin. No data crosses servers, and with one site the dashboards show no site switcher.
  - **Multiple sites**: one host app stores the statistics of other websites (any technology) whose visitors' browsers post to its collector, optionally on its own `collector_domain`; other Laravel sites may forward server-side counts in client mode, at the cost of one request across the network per counted request.
- One package, two modes: `mode = host` (stores data, serves the collector, dashboard and API) or `client` (only sends server-side events, creates no tables, loads no routes).
- The script, collector and API routes are mounted on the configurable domain `collector_domain` (e.g. `stats.example.com`); when it is empty they are served on the current domain under `collector_path`.
- The collector routes use no session, CSRF or cookie middleware. `POST /api/event` answers `204` for everything, including rejected events (no information for probing); only a malformed request gets `400`.

## 4. Data model

The table prefix is configurable (default `stats_`). Every daily table is bucketed by the **site's timezone** (set per site, default UTC).

### 4.1 Sites and credentials

- `stats_sites`: id, public_id (26-character ULID, used in the script), name, domains (JSON array of allowed hostnames; `example.com` also allows `www.example.com`, `*.example.com` allows `example.com` and every subdomain), timezone, server_key_hash (client mode, sha256), locales (JSON list of URL path prefixes of the site's language versions, e.g. `["en","zh-cn"]`), default_locale (language of pages without a prefix), exclude_paths (JSON list of path patterns that are never counted, `*` matches any characters), public_counts (whether the public counts endpoint answers for the site), created_at, updated_at.
- `stats_api_tokens`: id, name, token_hash (sha256), site_ids (JSON, null = all sites), last_used_at, created_at, updated_at.

### 4.2 Temporary data (deleted within 48 hours after the day ends)

- `stats_salts`: date (primary key), salt (32 random bytes, hex). Created on first use of a date. The maintenance command deletes salts dated before UTC yesterday, which keeps every date still in use in any timezone from UTC-12 to UTC+14.
- `stats_uniques`: site_id, date, scope, visitor_hash; primary key on all four columns. Deduplicates visitors per report row. `scope` is the sha256 hex of the deduplicated object: `site`, `page:<path_hash>`, `source:<source_key>`, `dim:<dimension>:<value>`, `entry:<path_hash>:<source_key>`, `move:<from_hash>:<to_hash>`, or `event:` + sha256(kind|name|label_hash|target_hash|path_hash).
- `stats_visits`: site_id, visitor_hash, started_at, date, last_seen_at, pageviews, entry_path_hash, exit_path_hash, source_key, dimensions (JSON of the visit's country, device, browser, OS and browser language); primary key (site_id, visitor_hash, started_at). Visits in progress; after 30 minutes without activity a visit ends, its bounce, duration, exit page, page views per dimension and page views after its entry page are added to the aggregates and the row is deleted. Times are stored in UTC.

### 4.3 Daily aggregates (kept forever, or for `retention_days`)

- `stats_daily_totals`: site_id, date, pageviews, visitors, visits, bounces, duration_seconds (sum over ended visits), events (outbound clicks, downloads and custom events), outbound. Unique (site_id, date).
- `stats_daily_pages`: site_id, date, path (≤ 512), path_hash, pageviews, visitors, entries, entry_visitors (visitors who entered here), exits, entry_pageviews (page views of the visits that entered here, added when they end), entry_outbound and entry_events (outbound clicks and other events of the visits that entered here), engaged_views, engaged_seconds, time_10/30/60/180, scroll_25/50/75/100 (time on page and scroll depth, 5.3), title, page_type, content_group (the latest values the page reported). Unique (site_id, date, path_hash).
- `stats_daily_sources`: site_id, date, source_key, source_type (utm, search, social, ai, referral, direct), source (referrer host without `www.`, or utm_source), utm_medium, utm_campaign, utm_content, utm_term, visits, visitors, pageviews, bounces, outbound, events. Unique (site_id, date, source_key). A visit's page views, outbound clicks and events belong to the source of its entry.
- `stats_daily_entries`: site_id, date, path, path_hash, source_key, source_type, source, utm_campaign, visits, visitors. Unique (site_id, date, path_hash, source_key). Landing pages per source.
- `stats_daily_transitions`: site_id, date, from_path, from_hash, to_path, to_hash, count, visitors. Unique (site_id, date, from_hash, to_hash). Moves from one page to another within a visit.
- `stats_daily_dimensions`: site_id, date, dimension (country, device, browser, os, language, locale), value, visits, visitors, pageviews. Unique (site_id, date, dimension, value). `language` is the browser's language; `locale` is the site language version of the page (from the site's `locales`), counted per page view.
- `stats_daily_events`: site_id, date, kind (outbound, download, custom, ai_fetch), name (custom event name or AI fetcher name), label, label_hash, target (URL without query string: the outbound or download link, or the link of a custom event), target_hash, path, path_hash, count, visitors. Unique (site_id, date, kind, name, label_hash, target_hash, path_hash). AI fetches have no visitor, so their visitors stay 0.

## 5. Collection

### 5.1 Browser payload

`POST /api/event`, `Content-Type: text/plain` (`application/json` is also accepted), JSON body ≤ 4 KB. Text fields must not contain control characters:

| Field | Meaning |
| --- | --- |
| `s` | Site public_id (ULID) |
| `t` | Type: `pageview`, `outbound`, `download`, `event`, `engagement` |
| `u` | Page URL (http/https, ≤ 2048, no whitespace or control characters). The script sends only the path plus UTM and click-id parameters; the server stores only the path |
| `r` | `document.referrer` (pageview only, ≤ 2048); ignored when it is not valid UTF-8 or contains control characters |
| `pt` | Page title (pageview, optional, ≤ 200 characters) |
| `py` | Page type (pageview, optional, `[a-z0-9_-]{1,40}`) |
| `pg` | Content group (pageview, optional, ≤ 120 characters): a key shared by the language versions of one piece of content |
| `n` | Event name (`event` only, `[a-z0-9_.-]{1,64}`) |
| `l` | Label (`event`, `outbound`, `download`; optional, ≤ 120 characters) |
| `g` | Target URL (http/https, ≤ 2048): required for `outbound` and `download`, optional for `event`; stored as `scheme://host/path` |
| `e`, `sp` | `engagement` only: seconds the page was visible (0–86400) and the deepest scroll in percent (0–100), totals so far |
| `e0`, `sp0` | `engagement` only, optional: the totals already reported for this page view (default 0; not greater than `e`, `sp`) |
| `w` | Screen width in pixels (integer 0–10000), used to tell tablets from phones |

### 5.2 Processing order

1. Check the content type, parse the JSON, validate types and lengths. A malformed request gets `400`; everything after this step answers `204`.
2. The site must exist and the host of `u` must be one of its domains. If the browser sends an origin (`Origin`; when it is missing, empty or `null`, the `Referer`), its host must also be one of the site's domains, otherwise the event is dropped. When neither is sent (pages with `Referrer-Policy: no-referrer` or `same-origin`, sandboxed iframes), only `u` is checked. `localhost` hosts and IP addresses are always dropped.
3. Rate limits per site (3000 per minute) and per IP (120 per minute; IPv6 counted per /64). The IP limiter key is `HMAC-SHA256(app key, date|IP)`, so cache keys cannot be reversed to an IP without the app key and cannot be linked across days.
4. An empty User-Agent, or one matching the bot list or the AI fetcher list, is dropped.
5. Visitor hash, then the per-visitor rate limit (60 per minute).
6. Path normalisation: query string and fragment removed, ≤ 512 characters, must start with `/` and not with `//`, no control characters, valid UTF-8. Paths matching the site's `exclude_paths` are dropped.
7. Write (5.3). A failed write is only logged, with the exception class and never the message (query exception messages contain bound values such as visitor hashes and paths).

Per site, day and table, at most `max_new_rows_per_day` (default 5000) new aggregate rows are created (pages, sources, events, dimensions, landing pages, page moves); further values are counted under `(other)`, so forged paths, sources and events cannot grow the tables without bound.

### 5.3 Write rules

- **pageview**:
  - Find the visitor's latest visit on the site: if its `last_seen_at` is within 30 minutes, continue it, otherwise open a new visit. Finding or opening the visit is serialised per visitor with a Laravel cache lock, so two parallel first page views cannot open two visits.
  - New visit: classify the source (5.4); `stats_daily_totals.visits` +1, `stats_daily_sources.visits` +1, entry page `entries` +1 (and `entry_visitors` once per visitor), the landing-page row (`stats_daily_entries`) `visits` +1, each dimension (country, device, browser, OS, browser language, and the site language of the entry page) `visits` +1 with its visitors deduplicated through `stats_uniques`.
  - Every page view: `pageviews` +1 on totals, the page row (title, type and group overwritten when the page sent them) and the visit's source row; the site language row `pageviews` +1; when the visit was already on another page, the move from that page to this one +1; visitors deduplicated through `stats_uniques`; the visit's `pageviews`, `last_seen_at` and `exit_path_hash` are updated.
- **engagement**: on the page's row, `engaged_seconds` + (new total − reported total, each capped at 1800 s); `engaged_views` +1 when nothing was reported before (`e0` and `sp0` are 0); `time_10/30/60/180` and `scroll_25/50/75/100` +1 for each threshold first reached in this report. The open visit's `last_seen_at` is updated, so visit duration includes the time on the last page. Not counted as an event.
- **outbound / download / event**: `stats_daily_events.count` +1 with visitors deduplicated; `totals.events` +1 (and `totals.outbound` +1 for outbound clicks). Events do not open a visit; when one is open they update its `last_seen_at` and are credited to it: an outbound click to `outbound` of the visit's source row and `entry_outbound` of its entry page, a download or custom event to `events` and `entry_events`. Outbound and download rows have an empty name (only custom events and AI fetches have one). AI fetches are not counted in `totals.events`.
- A visitor who crosses the site's local midnight becomes a new visitor with a new visit (the daily salt changes), so nothing is linked across days.
- **Ending visits** (maintenance command, every 5 minutes): for each visit whose `last_seen_at` is more than 30 minutes ago: `pageviews = 1` counts a bounce (`bounces` +1 on totals and the source row); the duration `last_seen_at - started_at` is added to totals; the exit page gets `exits` +1; the visit's page views are added to `pageviews` of its dimension rows and to `entry_pageviews` of its entry page; the row is deleted. These figures therefore fill in up to 30 minutes after a visit's last activity.
- **Counter writes**: `insertOrIgnore` the `stats_uniques` row first (inserted = new visitor for that scope), then `UPDATE col = col + n`; when no row was updated, `insertOrIgnore` the row and update again. Each counter write runs in its own transaction (a savepoint inside an outer one), so a failure can be cleaned up on PostgreSQL as well. Concurrency errors such as deadlocks are retried, up to 3 attempts. When the counter write fails, the `stats_uniques` row inserted for it is deleted again. Portable across MySQL, PostgreSQL and SQLite.

### 5.4 Source classification

- `utm_source` that is an AI host (with or without `www.`) and no `utm_medium` or `utm_campaign` → `ai`: AI assistants such as ChatGPT tag the links they show with `utm_source=<their host>` only.
- Other `utm_source` present → `utm` (with medium, campaign, content and term).
- No referrer → the first matching click id, otherwise `direct`.
- Referrer present, in this order: the site's own hosts (not a new source, counted as `direct`) → AI → search → social → click id → `referral`.
- AI comes before search, otherwise `gemini.google.com` would be classified as search by `google.`. AI hosts match exactly (with or without `www.`). Search and social entries match on domain label boundaries, not substrings: `google.` matches `google.com` and `www.google.co.uk`; `t.co` matches `t.co` and its subdomains but not `microsoft.com`.
- The lists (search hosts, social hosts, AI hosts, click ids with the source they imply) are in the config and can be extended.
- `source_key = sha256(source_type|source|medium|campaign|content|term)`. UTM values are trimmed, stripped of control characters and cut to 128 characters.

### 5.5 Dimensions

- Country: the request header named by `country_header` (default `CF-IPCountry`; two upper-case letters, excluding `XX` and `T1`); when it gives none, a lookup of the IP address in the local MaxMind DB file `country_database` (its `country.iso_code`, the same check); otherwise `unknown`. The database is opened once per request; a file that cannot be read logs a warning and leaves the country `unknown` without losing the event. No external geolocation service is called.
- Device, browser, OS: lightweight built-in User-Agent rules (no external library). Devices are desktop, mobile and tablet; the screen width `w` is only used to tell tablets from phones (600–1100 px counts as a tablet).
- Language: the primary subtag of the first `Accept-Language` entry (e.g. `en`, `zh`); `unknown` if it is not 2–3 letters.
- Site language (`locale`): for sites with `locales`, the first path segment when it is one of them, otherwise `default_locale` (or `(default)`). Sites without `locales` have no such dimension.

### 5.6 Counting on the server

- **AI fetches**: AI fetchers do not run JavaScript, so the `TrackAiFetches` middleware counts them. It recognises requests by the `ai_fetchers` User-Agent list and counts GET requests answered with 2xx or 3xx, after the response has been sent (`terminate`). `ai_user_fetchers` marks the fetchers that act for a person asking an assistant; the others are crawlers. At most `server_events_per_minute` per site and minute.
- **Page views** (optional, instead of the script's page views): the `TrackPageviews` middleware counts GET requests answered with 2xx and an HTML body, except prefetches, bots and the package's own pages. The page URL keeps only the attribution parameters, like the script; the referrer is the `Referer` header. Visitors without JavaScript are counted, and 404s or forged paths are not. `Stats::page(title, type, group)` describes the page during the request. A site counted this way must not also send page views from the script; it loads the script with `data-pageviews="off"` to keep its events.
- **Events from the server**: `Stats::event(name, label, target)` counts a custom event for the current request's visitor after the response, e.g. in a controller that redirects to a checkout.
- **Host mode**: these are recorded directly, without HTTP, through the same collector checks (site, domains, rate limits, bots, excluded paths). The site is the one set in `host_site`; when that is empty, the site is found by the request's `Host` header (only safe when the web server rejects unknown host names; the oldest site when several match). `@statsScript` uses the same site, prints its tag only on the site's own domains (the tag's URL follows the request host) and prints nothing when the lookup fails, so statistics never break a page.
- **Client mode**: they are sent as `POST /api/server-event` to `client.endpoint` with `Authorization: Bearer <site server key>`, a 2-second timeout, after the response; failures are logged with the exception class only. Bodies (≤ 8 KB): `{site, kind: "ai_fetch", name, path}`; `{site, kind: "pageview", url, referrer, title, page_type, content_group, ip, user_agent, country, accept_language}`; `{site, kind: "event", url, name, label, target, ip, user_agent, country, accept_language}`. The visitor's IP and User-Agent are forwarded to the host because the visitor hash is computed there; they are not stored. The endpoint answers `401` for an unknown site or a wrong key, `400` for a malformed body, otherwise `204` (also when rate limited: AI fetches by `server_events_per_minute`, page views and events by the collector's limits).

## 6. Tracking script

- A single dependency-free file of about 8 KB, served by the package route `GET /js/stats.js` with an ETag and public caching for one day. The collector URL is derived from the script's `src` (or set with `data-api`).
- Only the path and the UTM and click-id parameters leave the page, never the `#` fragment or other query parameters (which may contain tokens or e-mail addresses); outbound and download targets are sent as `scheme://host/path` only.
- Automatic: a page view on load; one more each time the path changes through `history.pushState`, `replaceState` or `popstate` (single-page apps); a click (left or middle button) on a link to a file with a download extension sends `download` (on any host), otherwise a link to another host sends `outbound`. The built-in extension list matches `download_extensions` in the config (pdf, zip, dmg, exe, msi, apk, ipa, deb, rpm, xlsx, docx, csv, mp4, …); `data-download-extensions` on the script tag replaces it.
- Page details: each page view sends `document.title` and the `<meta name="n2n-stats:type">` and `<meta name="n2n-stats:group">` contents. A page view triggered by a history change is sent on the next task (`setTimeout 0`) with the path and URL captured at the change, so the title, meta tags and skip tag are read after the router has rendered the new page. A page with `<meta name="n2n-stats:skip">` (e.g. the 404 page) sends nothing.
- Time on page and scroll depth: the script measures how long the page is visible and the deepest scroll, and sends `engagement` when the page is hidden (`visibilitychange`), left (`pagehide`) or replaced by a single-page-app navigation (before the new page view). Each report carries the totals so far and those already reported, and is sent only when something increased; the depth never decreases within a page view, and a page that was never visible sends nothing. At a single-page-app navigation the report uses only what was measured on the page being left (its depth and skip tag are sampled once it has rendered), because the router may already have replaced the DOM. It is sent also with `data-pageviews="off"`.
- Links: outbound and download events send the link as `g` and the `data-stats-label` of the link or its closest ancestor as `l` (e.g. the project a card belongs to).
- In a host app's own layout, the Blade directive `@statsScript` (optionally with data attributes, e.g. `['data-pageviews' => 'off']`) prints the tag for the app's site; in client mode it points to the configured host and site.
- Manual: `window.n2nStats('name', {label: '…', target: '/checkout'})` (calls queued before the script loaded are sent too), or an element with `data-stats-event="name"` (optionally `data-stats-label`); a click sends the event, with the link as target when the element is or is inside a link (internal links included).
- No cookies, `localStorage` or `sessionStorage`. Nothing is sent when `navigator.webdriver` is true, on `file://` pages, or on `localhost`, `127.0.0.1` and `[::1]`. Paths matching `data-exclude-paths` on the script tag (comma-separated, `*` matches any characters) are not sent. `data-pageviews="off"` turns off page views (for sites that count them on the server) and keeps events.
- Sending uses `navigator.sendBeacon`, falling back to `fetch(..., {keepalive: true, credentials: 'omit'})`, as `text/plain` so there is no CORS preflight.

## 7. Dashboard, Filament, API

- **Dashboard**: served unless `dashboard_enabled` is false (a host that shows the statistics in its own admin, such as the Filament plugin, turns it off so there is a single entry); route prefix `dashboard_path` (default `stats`), mounted with `dashboard_middleware` (default the `web` group) and the `viewStats` gate (the package defines it to deny everyone; the host app grants access). Pages are standalone, or inside the host's Blade admin layout (`dashboard_layout`: a layout to extend, whose other sections can get fixed text, or a layout component), where they keep the light theme; an optional back link (`dashboard_back_url`) sits at the top. Site management is opened from Site settings at the top right. Server-rendered Blade with inline SVG charts and a little plain JS, no front-end build and no CDN. All text is translated (`lang/en`, `lang/zh_CN`) and follows the app locale.
  - Light and dark themes from design tokens (following the system setting), down to phone width, reduced motion respected.
  - Top: site switcher (only with more than one site) and range (today, yesterday, 7, 15, 30 and 90 days, this month, last month, custom; at most 366 days). Changes compare complete days only: a range that includes today compares its days up to yesterday with the same number of days before; "today" compares yesterday with the day before. The page states both compared ranges and notes when today is still in progress.
  - Overview: visitors, visits, page views, bounce rate and average visit duration as primary figures; pages viewed, views per visit, average time on page, events, outbound clicks and visitors online now (visits in progress with activity in the last 5 minutes) as secondary ones; each with its change and a sparkline of its daily values. A daily chart of one chosen metric (visitors, visits, page views, events or outbound clicks), with the same number of days before as a dashed line, each day's values on hover or keyboard focus, and its data table. Views per visit are the page views of ended visits over the ended visits (`entry_pageviews` and `exits` of `stats_daily_pages`), so page views without visit data are left out.
  - Reports in tabs, covering every report of the API: Pages (pages with title and type, entry pages with the page views, outbound clicks and events after entry, exit pages, time on page and scroll depth per page, page types, content groups, page moves), Sources (source types, sources, UTM campaigns, landing pages by source, campaign landing pages, search landing pages; with page views, outbound clicks and events per source), Visitors (countries, devices, browsers, operating systems, browser languages, site languages; visits, visitors and page views), Events (custom events with label and target, outbound links and downloads with label, events per page, events per site language version of their page), AI (AI visits, visitors and fetches with a daily chart, AI referrals, AI landing pages, AI fetchers as crawler or user-triggered with the number of pages fetched, fetched pages). Each report is a card with its main column, main figure and share: the top 10 rows, "show all" for the top 50, and an "All columns" view of the full table for reports with more columns; tables can be sorted by any column; source types, countries, devices, browsers, operating systems, site languages and page types also have a donut chart. Charts above the tables of each tab (`Http\View\Charts`, inline SVG and HTML shared with the Filament page): time on page and scroll depth of all pages, content groups and page flow (Pages); daily visitors per source type and the bounce rate of the sources with the most visits (Sources); a world map of visitors per country (Visitors; country shapes from world-atlas 110m with points for the countries too small for it, built by `scripts/build-world-map.php`, licences in `resources/data/world-map.LICENSES.txt`); the daily count of the top custom events and the events per page view of the pages with the most events (Events); AI fetchers (AI). Wide charts span the tab; the other charts and the reports alternate between two columns that stack their cards, one column in the same order on narrow screens. The look follows a default Filament panel (zinc grays, amber accent; inside Filament the panel's primary colour). Each report can be exported as CSV (up to 500 rows, with spreadsheet formulas neutralised).
  - Site management: add sites, edit name, domains, timezone, site languages, default language, excluded paths and public counts; show the embed snippet; reset the server key (shown once).
  - The footer states the definitions: numbers are for reference only; no cookies or browser storage; no IP addresses or User-Agents stored; the same person is not recognised across days or sites, so visitors over a range are the sum of daily unique visitors.
- **Filament** (5.x): loaded only when the host registers `StatsPlugin` in a panel, so Filament is optional; everything is visible with the `viewStats` gate. All of it is built from native Filament components (filters, stats overview, chart and table widgets, a resource, actions), so it takes the panel's look, colours and dark mode, and none of it uses the standalone dashboard's routes — hosts turn `dashboard_enabled` off for a single entry.
  - Statistics page (navigation group and label set on the plugin): site (only with more than one site) and range filters in the URL, KPI cards with the complete-day change and daily sparklines, the daily traffic chart, and the report tabs as Filament tables (sortable, searchable where there is text, 25 rows per page through the breakdown offsets, CSV export streamed by the page) with doughnut charts; the widgets of a tab load when the tab is shown.
  - Sites resource (optional, `sitesResource(false)`): not in the panel navigation, opened from the Site settings header action of the statistics page; list, create, edit with the same rules as the dashboard and `stats:site` (shared on the `Site` model), the embed code with a copy button, server key rotation shown once, delete with all statistics.
  - Widgets for the panel's own dashboard per site: visitors and page views for today and the last 7 days, with the change over complete days; the top 5 sources of the last 7 days.
- **Read API** (collector routes, `Authorization: Bearer <API token>`):
  - `GET /api/v1/sites`
  - `GET /api/v1/sites/{public_id}/summary` (totals, `partial`, and the complete-day `comparison`)
  - `GET /api/v1/sites/{public_id}/timeseries` (one zero-filled row per day: visitors, visits, page views, events, outbound clicks, pages viewed, views per visit, bounce rate, average visit duration, average time on page)
  - `GET /api/v1/sites/{public_id}/ai` (AI visits, visitors, page views and fetches, with a daily series)
  - `GET /api/v1/sites/{public_id}/breakdown/{report}?limit=&offset=&min_visitors=`, report one of `pages`, `entries`, `exits`, `engagement`, `page_types`, `content`, `transitions`, `source_types`, `sources`, `campaigns`, `landing_pages`, `campaign_pages`, `search_pages`, `countries`, `devices`, `browsers`, `os`, `languages`, `locales`, `outbound`, `downloads`, `events`, `event_pages`, `event_locales`, `ai_referrals`, `ai_pages`, `ai_fetchers`, `ai_fetched_pages`; `limit` 1–500 (default 50), `offset` pages through any number of rows (`has_more` says whether more follow), `min_visitors` leaves out rows with fewer visitors.
  - `GET /api/v1/sites/{public_id}/snapshot?limit=&min_visitors=` (summary, timeseries, AI and the top rows of every report in one call; `limit` 1–100, default 10)
  - The range is `from` and `to` (`YYYY-MM-DD` in the site's timezone, default the last 30 days, at most 366 days) or `period`, one of the dashboard's presets (`today`, `yesterday`, `7d`, `15d`, `30d`, `90d`, `month`, `last_month`), not combined with `from` or `to`. A token may be limited to some sites; 60 requests per minute per token.
  - `sites` returns `{data}`; the per-site endpoints return `{site, from, to, partial, …}` (`partial` is true when the range includes the site's today). Errors: missing or invalid token 401 (with `WWW-Authenticate: Bearer`), unknown site or report 404, token without access to the site 403, invalid parameter 422 (`{message, errors}`). Every authenticated call updates the token's `last_used_at`.
- **Public counts** (collector routes, no token): `GET /api/public/sites/{public_id}/counts?path[]=…&event[]=…` returns all-time page views and the last day with a view for up to 50 paths, and all-time counts per label for up to 20 custom event names, for showing view or like counts on the site itself. It answers only for sites with `public_counts` on (404 otherwise), with `Access-Control-Allow-Origin: *`, `Cache-Control: public, max-age=60`, an OPTIONS preflight, and a per-IP limit of `public_per_minute`.

## 8. Commands and scheduling

- `stats:install {--force} {--site=} {--domain=*}`: publishes the config; in host mode runs the migrations, creates the app's own site (timezone UTC, like `stats:site create`) when `--site` and `--domain` are given — reusing the site that already counts the first domain, refusing when another site counts any of them, and stopping when the migrations did not run — and prints the next steps for both setups (gate, scheduler, `@statsScript` and optional middleware for a single site, `stats:site create` and snippets for other websites, the Filament plugin); in client mode prints the client steps.
- `stats:site {create|list|update|delete|key}`: manages sites, with `--name`, `--domain`, `--timezone`, `--locale`, `--default-locale`, `--exclude-path` and `--public-counts`/`--no-public-counts`. `create` prints the embed snippet (with `collector_domain` set, as `https://` on that domain, since the CLI has no request to take the scheme from; otherwise from `APP_URL`); `delete` removes the site with all its statistics (asks first, `--force` skips); `key` creates the client-mode server key and prints it once with the client's `.env` lines, replacing any previous key.
- `stats:token {create|list|revoke}`: API tokens, limited to sites with repeatable `--site`; the token is shown once and only its sha256 is stored.
- `stats:maintain`: ends idle visits (5.3), deletes expired salts and deduplication data, deletes aggregates older than `retention_days`. In host mode the package schedules it every 5 minutes (without overlapping).

## 9. Configuration (`config/stats.php`)

mode, table_prefix, collector_domain, collector_path, dashboard_enabled (true), dashboard_path, dashboard_middleware, dashboard_layout (null = standalone), dashboard_back_url, dashboard_back_label, host_site, visit_timeout_minutes (30), retention_days (null = forever), rate_limits (per_site_per_minute 3000, per_ip_per_minute 120, per_visitor_per_minute 60, server_events_per_minute 600, api_per_minute 60, public_per_minute 120), bot_user_agents, ai_fetchers, ai_user_fetchers, search_hosts, social_hosts, ai_hosts, click_ids, max_new_rows_per_day (5000), download_extensions; client mode: client.endpoint, client.site, client.key, client.timeout_seconds (2).

## 10. Privacy statement

No cookies and no browser storage; IP addresses and User-Agent strings are not stored (in client mode, server-side page views forward them to the host, which uses them only to compute the hash); the visitor hash uses a random daily salt that is deleted within 48 hours after the day ends, so visitors cannot be linked across days or sites; country comes from a CDN request header or a local country database; only daily aggregate counts are stored. The data stays on the site owner's own server and is not sent to third parties. `PRIVACY.md` has the full list and a paragraph for privacy policies.

## 11. Testing and quality

- PHP: Pest with Orchestra Testbench. Tests run on SQLite in memory by default and in CI also on MySQL and PostgreSQL. Covered: validation and rejection, source classification, bots and AI fetchers, hashing and salt rotation, visit splitting and bounces, deduplication, aggregate writes (page details, site languages, landing pages, page moves, event attribution), maintenance command, server-side page views and events in host and client mode, gate, API tokens, scopes, paging and snapshot, public counts, dashboard rendering in both languages, the Filament page and widgets.
- JavaScript: `node --test` with jsdom: automatic page views with title and meta tags, the skip tag, single-page-app navigation, outbound links and downloads with labels, `data-` attribute events with targets, the manual API, excluded paths, webdriver and localhost.
- Pint, PHPStan (level 6), GitHub Actions (PHP 8.3 and 8.4; Laravel 12 and 13).

## 12. Releases

Releases are tagged on GitHub (`n2ns/laravel-site-stats`) and published to Packagist.
