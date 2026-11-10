# N2N Stats

Cookieless, multi-site web analytics for Laravel. One Laravel app collects page views, sources,
outbound clicks, downloads, custom events and AI fetches for any number of websites, and stores
**only daily aggregates** in your own database.

`n2ns/laravel-stats` is made by [Datafrog LLC](https://datafrog.io) as part of
[N2NS Lab](https://n2ns.com).

## Why

- **No cookies, no browser storage.** The tracking script never reads or writes cookies,
  `localStorage` or `sessionStorage`, so there is nothing to ask consent for in a cookie banner.
- **Aggregates only.** There is no table of individual page views. Every report is a daily
  counter (page views, visits, visitors, bounces, …) per site, page, source, country, device and so on.
- **No personal data kept.** IP addresses and User-Agent strings are used for a moment to compute a
  hash and are never stored. The hash uses a random salt that changes every day and is deleted
  within 48 hours after the day ends, so a visitor cannot be followed across days or across sites.
- **Multi-site.** One installation serves many websites with one line of script each; they do not
  have to be Laravel apps.
- **Self-hosted, no third parties.** Data stays on your server. Country comes from your CDN's
  request header; no IP address is ever sent to a geolocation service.

### What is and isn't collected

| Collected (as daily counts) | Not collected |
| --- | --- |
| Page path (without query string or fragment) | IP addresses |
| Referrer host, UTM parameters, source type | Full User-Agent strings |
| Country (from the `CF-IPCountry` header) | Cookies or any browser storage |
| Device class, browser, operating system, language | Individual page views or sessions |
| Outbound link and download targets (without query string) | Identifiers that last longer than a day |
| Custom event names and labels | Anything about a visitor across days or sites |
| AI fetcher requests (server side) | |

See [PRIVACY.md](PRIVACY.md) for the full list and a paragraph you can adapt for your privacy policy.

## Requirements

- PHP ^8.3
- Laravel 12 or 13
- MySQL, PostgreSQL or SQLite

## Installation

```bash
composer require n2ns/laravel-stats
php artisan stats:install
```

`stats:install` publishes `config/stats.php`, runs the migrations (pass `--force` in production)
and prints the next steps below.

## Host setup

The app that stores the data runs in **host mode** (the default, `STATS_MODE=host`).

### 1. Choose where the collector lives

The tracking script, the collector and the read API can run on their own domain, for example
`stats.example.com`, while the dashboard stays on your app's normal domain:

```dotenv
STATS_COLLECTOR_DOMAIN=stats.example.com
```

Leave it empty to serve them on the app's own domain under `STATS_COLLECTOR_PATH` (empty by default,
so the script is at `/js/stats.js`).

For a separate stats subdomain:

- **DNS:** add a record for `stats.example.com` pointing to the server of your app.
- **Web server:** point the subdomain at the same Laravel app, e.g. an Nginx `server` block with
  `server_name stats.example.com;` and the same `root` and PHP handling as your app, plus a TLS
  certificate for the name.
- **Country data:** the country is read from the `CF-IPCountry` request header. Put the domain behind
  Cloudflare with the proxy enabled (or another CDN that sends this header); without it every
  visit is counted with the country `unknown`.
- **Real client IP:** behind a proxy or CDN, configure Laravel's trusted proxies so that
  `$request->ip()` is the visitor's address. The address is only used for the daily hash and rate
  limits, but if all requests appear to come from the proxy, all visitors collapse into one.

### 2. Allow access to the dashboard

The dashboard is guarded by the `viewStats` gate, which denies everyone until you define it, for
example in `AppServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewStats', fn ($user) => $user->is_admin);
```

### 3. Run the scheduler

The package schedules `stats:maintain` every five minutes. It ends visits that have been idle for
30 minutes, deletes expired salts and per-day deduplication data, and removes aggregates older than
`retention_days`. Make sure Laravel's scheduler runs:

```cron
* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
```

## Adding sites

```bash
php artisan stats:site create --name="Example" --domain=example.com --domain=*.example.org --timezone=Europe/Berlin
```

The command prints the site's public id and its snippet. Add the snippet to the `<head>` of every page:

```html
<script defer data-site="01JC7…" src="https://stats.example.com/js/stats.js"></script>
```

- **Domains** are the hostnames allowed to send data for the site. `example.com` also accepts
  `www.example.com`; `*.example.com` accepts `example.com` and every subdomain. Events from other
  hosts, from `localhost` and from IP addresses are dropped.
- **Timezone** decides where a day starts and ends for the site's daily aggregates (default `UTC`).

Other actions:

```bash
php artisan stats:site list
php artisan stats:site update --site=<public id> --name="New name" --domain=example.com --timezone=UTC
php artisan stats:site delete --site=<public id>          # asks first; --force skips the question
php artisan stats:site key --site=<public id>             # server key for client mode, see below
```

`update` replaces the domain list when any `--domain` is given. `delete` removes the site and all
of its statistics. Sites can also be added and edited on the dashboard.

## The tracking script

The script is a single dependency-free file of about 2 KB.

**Automatic:**

- A page view when the page loads, and another one each time the path changes through
  `history.pushState`, `history.replaceState` or `popstate`, so single-page apps are counted
  without extra code.
- An `outbound` event when a link to another host is clicked (left or middle button).
- A `download` event when a link to a file with one of the configured `download_extensions`
  (pdf, zip, dmg, exe, apk, …) is clicked.

**Custom events**, either from JavaScript:

```js
window.n2nStats('signup', { label: 'pro-plan' });
```

or without JavaScript, by marking an element; a click sends the event:

```html
<button data-stats-event="signup" data-stats-label="pro-plan">Sign up</button>
```

Event names match `[a-z0-9_.-]{1,64}`; labels are at most 120 characters.

**Exclusions:** nothing is sent when `navigator.webdriver` is true (automated browsers), on
`file://` pages, or on localhost, `127.0.0.1` and `[::1]`. A `data-exclude-paths` attribute on the
script tag excludes paths: a comma-separated list where `*` matches any characters, including `/`
(for example `data-exclude-paths="/admin*,/preview/*"`). Downloads are detected by file extension;
`data-download-extensions="pdf,zip"` on the script tag replaces the built-in list. Requests whose User-Agent is
empty or matches the `bot_user_agents` list are dropped by the collector.

Data is sent with `navigator.sendBeacon` (or `fetch` with `keepalive` and no credentials) as a
`text/plain` request, so browsers do not send a CORS preflight. The collector always answers
`204 No Content`, also when it drops an event; only malformed requests get `400`.

## Client mode and AI fetches

AI crawlers and assistants (GPTBot, ChatGPT-User, ClaudeBot, PerplexityBot, …) do not run
JavaScript, so they are counted on the server by the `TrackAiFetches` middleware. Each matching
request is recorded as an `ai_fetch` event with the fetcher's name and the requested path.

- **On the host app**, add the middleware to your web routes; it writes to the database directly.
  Set `STATS_HOST_SITE` to the host's own site public id, so the site is not taken from the
  request's `Host` header. Only successful (2xx) GET responses are counted, at most
  `rate_limits.server_events_per_minute` per site and minute.
- **On another Laravel site**, install the same package in **client mode**. It creates no tables
  and only sends these events to the host. Create a server key on the host:

  ```bash
  php artisan stats:site key --site=<public id>
  ```

  The key is shown once, together with the lines for the client's `.env`:

  ```dotenv
  STATS_MODE=client
  STATS_CLIENT_ENDPOINT=https://stats.example.com
  STATS_CLIENT_SITE=<public id>
  STATS_CLIENT_KEY=<key>
  ```

  Running `key` again replaces the key; the old one stops working.

Register the middleware, for example in `bootstrap/app.php`:

```php
use N2ns\Stats\Http\Middleware\TrackAiFetches;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [TrackAiFetches::class]);
})
```

In client mode the event is sent after the response has been delivered, with a 2-second timeout;
failures are ignored and never affect the page.

## Dashboard

Open `/stats` (the `dashboard_path`) on your app's domain. It is plain server-rendered Blade with
inline SVG charts: no front-end build and no CDN.

- Site switcher and date ranges (today, yesterday, 7, 30 and 90 days, this month, last month,
  custom), compared with the previous period of the same length.
- Overview: visitors, visits, page views, views per visit, bounce rate, average visit duration,
  visitors online now (visits with activity in the last 5 minutes) and a daily chart.
- Reports: pages (views, visitors, entries, exits), sources by type and source, UTM campaigns,
  countries, devices, browsers, operating systems, languages, outbound links, downloads, custom
  events and AI (visits referred by AI assistants, AI fetchers and the pages they fetched). Each
  report shows the top 50 rows; the CSV export contains up to 500 rows.
- Site management: add sites, edit domains and timezone, show the embed snippet, reset the server key.

## Filament

If your app uses [Filament](https://filamentphp.com), register the plugin in your panel:

```php
use N2ns\Stats\Filament\StatsPlugin;

$panel->plugin(StatsPlugin::make());
```

It adds widgets with each site's visitors and page views for today and the last 7 days (with the
change against the previous period) and the top 5 sources of the last 7 days, linking to the dashboard.

## Read API

A read-only JSON API is served on the collector domain. Create a token:

```bash
php artisan stats:token create --name="Reporting" --site=<public id>   # repeat --site, or omit it for all sites
php artisan stats:token list
php artisan stats:token revoke --id=<token id>
```

The token is shown only once; only its SHA-256 hash is stored. Send it as a bearer token:

| Endpoint | Returns |
| --- | --- |
| `GET /api/v1/sites` | The sites the token can read |
| `GET /api/v1/sites/{public_id}/summary?from=&to=` | Totals for the range, with the previous period for comparison |
| `GET /api/v1/sites/{public_id}/timeseries?from=&to=` | Daily values for the range |
| `GET /api/v1/sites/{public_id}/breakdown/{report}?from=&to=&limit=` | Top rows of one report |

`report` is one of `pages`, `entries`, `exits`, `sources`, `source_types`, `campaigns`, `countries`,
`devices`, `browsers`, `os`, `languages`, `outbound`, `downloads`, `events`, `ai`. Dates are `YYYY-MM-DD` in the site's
timezone; a range covers at most 366 days. Requests are limited to 60 per minute per token.

```bash
curl -H "Authorization: Bearer $STATS_TOKEN" \
  "https://stats.example.com/api/v1/sites/01JC7…/summary?from=2026-11-01&to=2026-11-30"

curl -H "Authorization: Bearer $STATS_TOKEN" \
  "https://stats.example.com/api/v1/sites/01JC7…/breakdown/pages?from=2026-11-01&to=2026-11-30&limit=10"
```

## Configuration

Publish the file with `php artisan vendor:publish --tag=stats-config`. Main options:

| Key | Env | Default | Meaning |
| --- | --- | --- | --- |
| `mode` | `STATS_MODE` | `host` | `host` stores data and serves the collector, dashboard and API; `client` only sends AI fetch events to a host |
| `table_prefix` | `STATS_TABLE_PREFIX` | `stats_` | Prefix of all tables |
| `collector_domain` | `STATS_COLLECTOR_DOMAIN` | none | Domain for the script, collector and API; none = the current domain |
| `collector_path` | `STATS_COLLECTOR_PATH` | empty | Path prefix of those routes |
| `dashboard_path` | `STATS_DASHBOARD_PATH` | `stats` | Dashboard route prefix |
| `dashboard_middleware` | | `['web']` | Middleware of the dashboard routes (the `viewStats` gate is always applied) |
| `host_site` | `STATS_HOST_SITE` | none | Host mode: public id of the site whose AI fetches the middleware counts; none = found by the `Host` header |
| `visit_timeout_minutes` | | `30` | Idle time that ends a visit |
| `retention_days` | `STATS_RETENTION_DAYS` | none | Days to keep daily aggregates; none = forever |
| `rate_limits` | | | Collector limits per site (3000/min), per IP (120/min, IPv6 per /64) and per visitor (60/min); server events and API |
| `max_new_rows_per_day` | | `5000` | New daily rows per site, day and table; further values are counted under `(other)` |
| `bot_user_agents` | | | User-Agent substrings that are dropped as bots |
| `ai_fetchers` | | | User-Agent substrings counted as AI fetchers |
| `search_hosts`, `social_hosts`, `ai_hosts` | | | Referrer hosts that classify a source as search, social or AI |
| `click_ids` | | | Click-id query parameters (`gclid`, `fbclid`, …) and the source they imply |
| `download_extensions` | | | File extensions counted as downloads |
| `client.endpoint`, `client.site`, `client.key` | `STATS_CLIENT_*` | | Client mode: host URL, site public id and server key |

## Data model

All tables use the `table_prefix`. Daily tables are bucketed by the site's timezone.

| Table | Contents | Kept |
| --- | --- | --- |
| `stats_sites` | Name, public id, allowed domains, timezone, hash of the server key | Until deleted |
| `stats_api_tokens` | Name, hash of the token, allowed sites, last use | Until revoked |
| `stats_daily_totals` | Page views, visitors, visits, bounces, visit duration, events per day | `retention_days` |
| `stats_daily_pages` | Per path: page views, visitors, entries, exits | `retention_days` |
| `stats_daily_sources` | Per source (type, host or UTM source, medium, campaign, content, term): visits, visitors, page views, bounces | `retention_days` |
| `stats_daily_dimensions` | Per country, device, browser, OS, language: visits, visitors | `retention_days` |
| `stats_daily_events` | Outbound clicks, downloads, custom events and AI fetches per name, target and page: count, visitors | `retention_days` |
| `stats_salts` | The random salt of each day | Two days |
| `stats_uniques` | Visitor hashes already counted per day and report row | Two days |
| `stats_visits` | Visits in progress (hash, start, last activity, page views, entry and exit page, source) | Until 30 minutes idle |

## How the metrics are defined

- **Page view:** one per page load or single-page-app path change reported by the script.
- **Visitor:** a unique `sha256(daily salt + site + IP + User-Agent)` within one day. The salt
  changes daily, so the same person is a new visitor every day. **Visitors over a range of days are
  the sum of the daily unique visitors**, not a count of distinct people.
- **Visit:** page views and events from the same visitor with less than 30 minutes between them.
  After 30 minutes of inactivity the next page view starts a new visit. When the daily salt
  changes, the visitor hash changes too, so the next page view also starts a new visit.
- **Source:** decided when a visit starts, from UTM parameters, the referrer and click ids
  (in that order: UTM, AI, search, social, click id, other referrer, direct). Referrals from the
  site itself count as direct.
- **Bounce:** a visit that ended with exactly one page view. Bounce rate = bounces / visits.
- **Visit duration:** the time between the first and the last activity of a visit; the total of
  ended visits is stored, and the average is derived from it. A single page view without further
  events lasts 0 seconds.
- Bounces, durations and exit pages are counted when a visit ends (30 minutes after its last
  activity), so the most recent figures fill in with a delay.

## Limitations

- **The numbers are for reference only.** Anyone can send events from an allowed domain; domain
  checks, rate limits, bot filters and field validation reduce noise but are no guarantee.
- **No identification across days.** Returning visitors, multi-day unique visitors and
  cross-day funnels are deliberately impossible.
- **Pages with `Referrer-Policy: no-referrer` or `same-origin`** send beacons without an origin; they
  are accepted when the page URL belongs to the site, so the origin check only applies when a
  browser sends one.
- **Values beyond `max_new_rows_per_day`** for a site and day are grouped under `(other)`.
- **No conversions or revenue yet.** Goals, funnels and revenue tracking are not part of this version.
- Visitors without JavaScript (and crawlers other than the AI fetchers counted by the middleware)
  are not counted.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Please report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md).

## License

[Apache-2.0](LICENSE). Copyright 2026 Datafrog LLC.
