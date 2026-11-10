# N2N Stats (n2ns/laravel-stats) design

N2N Stats is an open-source, cookieless, multi-site web analytics package for Laravel. One Laravel app collects and stores the statistics of any number of websites; only daily aggregates are kept. This document records how the package works and why. Where it and the code disagree, the code is right.

## 1. Scope

| Topic | Design |
| --- | --- |
| Form | Laravel package `n2ns/laravel-stats`, product name N2N Stats, Apache-2.0 |
| Data | Page views, sources, entry and exit pages, outbound clicks, downloads, AI fetches, unique visitors, custom events. Conversions, revenue and funnels are not part of this version |
| Unique visitors | No cookies: `sha256(daily salt + site public id + IP + User-Agent)`. The salt is random per day and deleted within 48 hours after the day ends |
| User interface | A built-in dashboard (access decided by the host app's `viewStats` gate), optional Filament widgets, a read-only JSON API |
| Abuse resistance | Site id, domain and origin checks, rate limits, field validation, bot filters, a cap on new daily rows. The dashboard and the docs state that the numbers are for reference only |
| Retention | Daily aggregates are kept forever by default (configurable in days); salts and deduplication data are deleted within 48 hours after the day ends; a visit in progress ends after 30 minutes without activity and is then deleted |
| Out of scope | Cookie consent flows (not needed), any geolocation that sends IP addresses to a third party |

## 2. Techniques

- A page view is counted only when the browser reports it (the script), not from server logs; bots and AI fetchers are recognised by User-Agent lists.
- Sources are classified as UTM, search, social, AI referral, other referral or direct, with click ids as a fallback.
- Only daily aggregates are stored, plus a per-day deduplication table; no table of individual page views.
- Counter writes insert the deduplication row first and then increment; if the counter write fails, the deduplication row is removed again.
- Outbound clicks include the middle mouse button.
- Strict field validation and length limits on everything a browser sends.
- Visits are split after 30 minutes of inactivity, which gives bounce rate and visit duration.
- File downloads are detected automatically; ranges are compared with the previous period; visitors online now; device, browser, OS, country and language dimensions.
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
   ├── Filament widgets (optional)
   └── Read API    https://<collector>/api/v1/...             (Bearer token)

Other Laravel sites can install the same package in client mode:
   middleware recognises AI fetcher requests → POST https://<collector>/api/server-event (site server key)
```

- One package, two modes: `mode = host` (stores data, serves the collector, dashboard and API) or `client` (only sends server-side events, creates no tables, loads no routes).
- The script, collector and API routes are mounted on the configurable domain `collector_domain` (e.g. `stats.example.com`); when it is empty they are served on the current domain under `collector_path`.
- The collector routes use no session, CSRF or cookie middleware. `POST /api/event` answers `204` for everything, including rejected events (no information for probing); only a malformed request gets `400`.

## 4. Data model

The table prefix is configurable (default `stats_`). Every daily table is bucketed by the **site's timezone** (set per site, default UTC).

### 4.1 Sites and credentials

- `stats_sites`: id, public_id (26-character ULID, used in the script), name, domains (JSON array of allowed hostnames; `example.com` also allows `www.example.com`, `*.example.com` allows `example.com` and every subdomain), timezone, server_key_hash (client mode, sha256), created_at, updated_at.
- `stats_api_tokens`: id, name, token_hash (sha256), site_ids (JSON, null = all sites), last_used_at, created_at, updated_at.

### 4.2 Temporary data (deleted within 48 hours after the day ends)

- `stats_salts`: date (primary key), salt (32 random bytes, hex). Created on first use of a date. The maintenance command deletes salts dated before UTC yesterday, which keeps every date still in use in any timezone from UTC-12 to UTC+14.
- `stats_uniques`: site_id, date, scope, visitor_hash; primary key on all four columns. Deduplicates visitors per report row. `scope` is the sha256 hex of the deduplicated object: `site`, `page:<path_hash>`, `source:<source_key>`, `dim:<dimension>:<value>`, or `event:` + sha256(kind|name|target_hash|path_hash).
- `stats_visits`: site_id, visitor_hash, started_at, date, last_seen_at, pageviews, entry_path_hash, exit_path_hash, source_key; primary key (site_id, visitor_hash, started_at). Visits in progress; after 30 minutes without activity a visit ends, its bounce, duration and exit page are added to the aggregates and the row is deleted. Times are stored in UTC.

### 4.3 Daily aggregates (kept forever, or for `retention_days`)

- `stats_daily_totals`: site_id, date, pageviews, visitors, visits, bounces, duration_seconds (sum over ended visits), events. Unique (site_id, date).
- `stats_daily_pages`: site_id, date, path (≤ 512), path_hash, pageviews, visitors, entries, exits. Unique (site_id, date, path_hash).
- `stats_daily_sources`: site_id, date, source_key, source_type (utm, search, social, ai, referral, direct), source (referrer host without `www.`, or utm_source), utm_medium, utm_campaign, utm_content, utm_term, visits, visitors, pageviews, bounces. Unique (site_id, date, source_key). A visit's page views belong to the source of its entry.
- `stats_daily_dimensions`: site_id, date, dimension (country, device, browser, os, language), value, visits, visitors. Unique (site_id, date, dimension, value).
- `stats_daily_events`: site_id, date, kind (outbound, download, custom, ai_fetch), name (custom event name or AI fetcher name), target (outbound or download URL without query string; the label of a custom event), target_hash, path, path_hash, count, visitors. Unique (site_id, date, kind, name, target_hash, path_hash). AI fetches have no visitor, so their visitors stay 0.

## 5. Collection

### 5.1 Browser payload

`POST /api/event`, `Content-Type: text/plain` (`application/json` is also accepted), JSON body ≤ 4 KB:

| Field | Meaning |
| --- | --- |
| `s` | Site public_id (ULID) |
| `t` | Type: `pageview`, `outbound`, `download`, `event` |
| `u` | Page URL (http/https, ≤ 2048, no whitespace or control characters). The script sends only the path plus UTM and click-id parameters; the server stores only the path |
| `r` | `document.referrer` (pageview only, ≤ 2048) |
| `n` | Event name (`event` only, `[a-z0-9_.-]{1,64}`) |
| `l` | Event label (`event`, optional, ≤ 120 characters); for `outbound` and `download` the target URL |
| `w` | Screen width in pixels (integer 0–10000), used to tell tablets from phones |

### 5.2 Processing order

1. Check the content type, parse the JSON, validate types and lengths. A malformed request gets `400`; everything after this step answers `204`.
2. The site must exist and the host of `u` must be one of its domains. If the browser sends an origin (`Origin`; when it is missing, empty or `null`, the `Referer`), its host must also be one of the site's domains, otherwise the event is dropped. When neither is sent (pages with `Referrer-Policy: no-referrer` or `same-origin`, sandboxed iframes), only `u` is checked. `localhost` hosts and IP addresses are always dropped.
3. Rate limits per site (3000 per minute) and per IP (120 per minute; IPv6 counted per /64). The IP limiter key is `HMAC-SHA256(app key, date|IP)`, so cache keys cannot be reversed to an IP without the app key and cannot be linked across days.
4. An empty User-Agent, or one matching the bot list or the AI fetcher list, is dropped.
5. Visitor hash, then the per-visitor rate limit (60 per minute).
6. Path normalisation: query string and fragment removed, ≤ 512 characters, must start with `/` and not with `//`, no control characters, valid UTF-8.
7. Write (5.3). A failed write is only logged, with the exception class and never the message (query exception messages contain bound values such as visitor hashes and paths).

Per site, day and table, at most `max_new_rows_per_day` (default 5000) new aggregate rows are created (pages, sources, events, dimensions); further values are counted under `(other)`, so forged paths, sources and events cannot grow the tables without bound.

### 5.3 Write rules

- **pageview**:
  - Find the visitor's latest visit on the site: if its `last_seen_at` is within 30 minutes, continue it, otherwise open a new visit. Finding or opening the visit is serialised per visitor with a Laravel cache lock, so two parallel first page views cannot open two visits.
  - New visit: classify the source (5.4); `stats_daily_totals.visits` +1, `stats_daily_sources.visits` +1, entry page `entries` +1, each dimension (country, device, browser, OS, language) `visits` +1 with its visitors deduplicated through `stats_uniques`.
  - Every page view: `pageviews` +1 on totals, the page row and the visit's source row; visitors deduplicated through `stats_uniques` (scopes `site`, `page:<hash>`, `source:<key>`); the visit's `pageviews`, `last_seen_at` and `exit_path_hash` are updated.
- **outbound / download / event**: `stats_daily_events.count` +1 with visitors deduplicated; `totals.events` +1. Events do not open a visit; they only update the open visit's `last_seen_at`. Outbound and download rows have an empty name (only custom events and AI fetches have one). AI fetches are not counted in `totals.events`.
- A visitor who crosses the site's local midnight becomes a new visitor with a new visit (the daily salt changes), so nothing is linked across days.
- **Ending visits** (maintenance command, every 5 minutes): for each visit whose `last_seen_at` is more than 30 minutes ago: `pageviews = 1` counts a bounce (`bounces` +1 on totals and the source row); the duration `last_seen_at - started_at` is added to totals; the exit page gets `exits` +1; the row is deleted.
- **Counter writes**: `insertOrIgnore` the `stats_uniques` row first (inserted = new visitor for that scope), then `UPDATE col = col + n`; when no row was updated, `insertOrIgnore` the row and update again. Each counter write runs in its own transaction (a savepoint inside an outer one), so a failure can be cleaned up on PostgreSQL as well. Concurrency errors such as deadlocks are retried, up to 3 attempts. When the counter write fails, the `stats_uniques` row inserted for it is deleted again. Portable across MySQL, PostgreSQL and SQLite.

### 5.4 Source classification

- `utm_source` present → `utm` (with medium, campaign, content and term).
- No referrer → the first matching click id, otherwise `direct`.
- Referrer present, in this order: the site's own hosts (not a new source, counted as `direct`) → AI → search → social → click id → `referral`.
- AI comes before search, otherwise `gemini.google.com` would be classified as search by `google.`. AI hosts match exactly (with or without `www.`). Search and social entries match on domain label boundaries, not substrings: `google.` matches `google.com` and `www.google.co.uk`; `t.co` matches `t.co` and its subdomains but not `microsoft.com`.
- The lists (search hosts, social hosts, AI hosts, click ids with the source they imply) are in the config and can be extended.
- `source_key = sha256(source_type|source|medium|campaign|content|term)`. UTM values are trimmed, stripped of control characters and cut to 128 characters.

### 5.5 Dimensions

- Country: the `CF-IPCountry` request header (two upper-case letters, excluding `XX` and `T1`), otherwise `unknown`. No external geolocation service is called.
- Device, browser, OS: lightweight built-in User-Agent rules (no external library). Devices are desktop, mobile and tablet; the screen width `w` is only used to tell tablets from phones (600–1100 px counts as a tablet).
- Language: the primary subtag of the first `Accept-Language` entry (e.g. `en`, `zh`); `unknown` if it is not 2–3 letters.

### 5.6 Server-side events (AI fetches)

AI fetchers do not run JavaScript, so the `TrackAiFetches` middleware counts them on the server. It recognises requests by the `ai_fetchers` User-Agent list and counts only successful (2xx) GET requests, after the response has been sent (`terminate`).

- **Host mode**: the middleware writes to the database directly, without HTTP. The site is the one set in `host_site`; when that is empty, the site is found by the request's `Host` header (only safe when the web server rejects unknown host names). At most `server_events_per_minute` per site and minute.
- **Client mode**: the middleware sends `POST /api/server-event` to `client.endpoint` with `Authorization: Bearer <site server key>` and the JSON `{site, kind: "ai_fetch", name: <fetcher>, path}`, with a 2-second timeout; failures are ignored. Only `ai_fetch` is accepted. The endpoint answers `401` for an unknown site or a wrong key, `400` for a malformed event, otherwise `204` (also when the per-site `server_events_per_minute` limit is reached).

## 6. Tracking script

- A single dependency-free file of about 2 KB, served by the package route `GET /js/stats.js` with an ETag and public caching for one day. The collector URL is derived from the script's `src` (or set with `data-api`).
- Only the path and the UTM and click-id parameters leave the page, never the `#` fragment or other query parameters (which may contain tokens or e-mail addresses); outbound and download targets are sent as `scheme://host/path` only.
- Automatic: a page view on load; one more each time the path changes through `history.pushState`, `replaceState` or `popstate` (single-page apps); a click (left or middle button) on a link to a file with a download extension sends `download` (on any host), otherwise a link to another host sends `outbound`. The built-in extension list matches `download_extensions` in the config (pdf, zip, dmg, exe, msi, apk, ipa, deb, rpm, xlsx, docx, csv, mp4, …); `data-download-extensions` on the script tag replaces it.
- Manual: `window.n2nStats('name', {label: '…'})` (calls queued before the script loaded are sent too), or an element with `data-stats-event="name"` (optionally `data-stats-label`); a click sends the event.
- No cookies, `localStorage` or `sessionStorage`. Nothing is sent when `navigator.webdriver` is true, on `file://` pages, or on `localhost`, `127.0.0.1` and `[::1]`. Paths matching `data-exclude-paths` on the script tag (comma-separated, `*` matches any characters) are not sent.
- Sending uses `navigator.sendBeacon`, falling back to `fetch(..., {keepalive: true, credentials: 'omit'})`, as `text/plain` so there is no CORS preflight.

## 7. Dashboard, widgets, API

- **Dashboard**: route prefix `dashboard_path` (default `stats`), mounted with `dashboard_middleware` (default the `web` group) and the `viewStats` gate (the package defines it to deny everyone; the host app grants access). Server-rendered Blade with inline SVG charts and a little plain JS, no front-end build and no CDN.
  - Top: site switcher and range (today, yesterday, 7, 30 and 90 days, this month, last month, custom; at most 366 days), compared with the previous period of the same length.
  - Overview: visitors, visits, page views, views per visit, bounce rate, average visit duration, visitors online now (visits in progress with activity in the last 5 minutes); daily chart.
  - Reports in tabs: pages (views, visitors, entries, exits), entry and exit pages, source types, sources, UTM campaigns, countries, devices, browsers, operating systems, languages, outbound links, downloads, custom events, AI (visits referred by AI assistants, AI fetchers and the pages they fetched). Each table shows the top 50 rows; each report can be exported as CSV (up to 500 rows, with spreadsheet formulas neutralised).
  - Site management: add sites, edit name, domains and timezone, show the embed snippet, reset the server key (shown once).
  - The footer states the definitions: numbers are for reference only; no cookies or browser storage; no IP addresses or User-Agents stored; the same person is not recognised across days or sites, so visitors over a range are the sum of daily unique visitors.
- **Filament widgets**: loaded only when the host registers `StatsPlugin` in a panel, so Filament is optional; visible with the `viewStats` gate. Per site: visitors and page views for today and the last 7 days, with the change against the previous period; the top 5 sources of the last 7 days. All link to the dashboard.
- **Read API** (collector routes, `Authorization: Bearer <API token>`):
  - `GET /api/v1/sites`
  - `GET /api/v1/sites/{public_id}/summary?from=&to=` (with the previous period)
  - `GET /api/v1/sites/{public_id}/timeseries?from=&to=` (one zero-filled row per day)
  - `GET /api/v1/sites/{public_id}/breakdown/{report}?from=&to=&limit=`, report one of `pages`, `entries`, `exits`, `sources`, `source_types`, `campaigns`, `countries`, `devices`, `browsers`, `os`, `languages`, `outbound`, `downloads`, `events`, `ai`
  - Dates are `YYYY-MM-DD` in the site's timezone, default the last 30 days, at most 366 days; `limit` 1–500, default 50; a token may be limited to some sites; 60 requests per minute per token.
  - `sites` returns `{data}`; the per-site endpoints return `{site, from, to, data}`, and breakdown also `report`. Errors: missing or invalid token 401 (with `WWW-Authenticate: Bearer`), unknown site or report 404, token without access to the site 403, invalid parameter 422 (`{message, errors}`). Every authenticated call updates the token's `last_used_at`.

## 8. Commands and scheduling

- `stats:install {--force}`: publishes the config; in host mode runs the migrations and prints the next steps (define the gate, create a site, run the scheduler, optional middleware and Filament plugin); in client mode prints the client steps.
- `stats:site {create|list|update|delete|key}`: manages sites. `create` prints the embed snippet; `delete` removes the site with all its statistics (asks first, `--force` skips); `key` creates the client-mode server key and prints it once with the client's `.env` lines, replacing any previous key.
- `stats:token {create|list|revoke}`: API tokens, limited to sites with repeatable `--site`; the token is shown once and only its sha256 is stored.
- `stats:maintain`: ends idle visits, deletes expired salts and deduplication data, deletes aggregates older than `retention_days`. In host mode the package schedules it every 5 minutes (without overlapping).

## 9. Configuration (`config/stats.php`)

mode, table_prefix, collector_domain, collector_path, dashboard_path, dashboard_middleware, host_site, visit_timeout_minutes (30), retention_days (null = forever), rate_limits (per_site_per_minute 3000, per_ip_per_minute 120, per_visitor_per_minute 60, server_events_per_minute 600, api_per_minute 60), bot_user_agents, ai_fetchers, search_hosts, social_hosts, ai_hosts, click_ids, max_new_rows_per_day (5000), download_extensions; client mode: client.endpoint, client.site, client.key, client.timeout_seconds (2).

## 10. Privacy statement

No cookies and no browser storage; IP addresses and User-Agent strings are not stored; the visitor hash uses a random daily salt that is deleted within 48 hours after the day ends, so visitors cannot be linked across days or sites; country comes from a CDN request header; only daily aggregate counts are stored. The data stays on the site owner's own server and is not sent to third parties. `PRIVACY.md` has the full list and a paragraph for privacy policies.

## 11. Testing and quality

- PHP: Pest with Orchestra Testbench. Tests run on SQLite in memory by default and in CI also on MySQL and PostgreSQL. Covered: validation and rejection, source classification, bots and AI fetchers, hashing and salt rotation, visit splitting and bounces, deduplication, aggregate writes, maintenance command, gate, API tokens and scopes, dashboard rendering, Filament widgets.
- JavaScript: `node --test` with jsdom: automatic page views, single-page-app navigation, outbound links and downloads, `data-` attribute events, the manual API, excluded paths, webdriver and localhost.
- Pint, PHPStan (level 6), GitHub Actions (PHP 8.3 and 8.4; Laravel 12 and 13).

## 12. Releases

Releases are tagged on GitHub (`n2ns/laravel-stats`) and published to Packagist.
