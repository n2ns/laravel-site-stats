# N2N Stats

Cookieless web analytics for Laravel, for a single site or for many. Install it in a site's own
Laravel app to count that site in its own database and admin, or run one Laravel app that counts any
number of websites. It collects page views, sources, outbound clicks, downloads, custom events, time
on page and AI fetches, and stores **only daily aggregates**.

`n2ns/laravel-site-stats` is made by [Datafrog LLC](https://datafrog.io) as part of
[N2NS Lab](https://n2ns.com).

## Why

- **No cookies, no browser storage.** The tracking script never reads or writes cookies,
  `localStorage` or `sessionStorage`, so there is nothing to ask consent for in a cookie banner.
- **Aggregates only.** There is no table of individual page views. Every report is a daily
  counter (page views, visits, visitors, bounces, …) per site, page, source, country, device and so on.
- **No personal data kept.** IP addresses and User-Agent strings are used for a moment to compute a
  hash and are never stored. The hash uses a random salt that changes every day and is deleted
  within 48 hours after the day ends, so a visitor cannot be followed across days or across sites.
- **Single site or many.** Count a Laravel app's own pages, with the data in its own database and the
  statistics in its own admin; or let one installation count many websites with one line of script
  each (they do not have to be Laravel apps). Both work in the same installation.
- **Self-hosted, no third parties.** Data stays on your server. Country comes from your CDN's
  request header; no IP address is ever sent to a geolocation service.

### What is and isn't collected

| Collected (as daily counts) | Not collected |
| --- | --- |
| Page path (without query string or fragment), page title, page type and content group | IP addresses |
| Referrer host, UTM parameters, source type | Full User-Agent strings |
| Country (from the `CF-IPCountry` header) | Cookies or any browser storage |
| Device class, browser, operating system, browser language, site language version | Individual page views or sessions |
| Outbound link, download and event targets (without query string) | Identifiers that last longer than a day |
| Custom event names and labels; moves between pages within a visit | Anything about a visitor across days or sites |
| AI fetcher requests (server side) | |

See [PRIVACY.md](PRIVACY.md) for the full list and a paragraph you can adapt for your privacy policy.

## Requirements

- PHP ^8.3
- Laravel 12 or 13
- MySQL, PostgreSQL or SQLite

## Installation

```bash
composer require n2ns/laravel-site-stats
php artisan stats:install
```

`stats:install` publishes `config/stats.php`, runs the migrations (pass `--force` in production)
and prints the next steps. The app that stores the data runs in **host mode** (the default,
`STATS_MODE=host`). Then, in every setup:

1. **Allow access to the statistics.** They are guarded by the `viewStats` gate, which denies everyone
   until you define it, for example in `AppServiceProvider::boot()`:

   ```php
   use Illuminate\Support\Facades\Gate;

   Gate::define('viewStats', fn ($user) => $user->is_admin);
   ```

2. **Run the scheduler.** The package schedules `stats:maintain` every five minutes. It ends visits
   that have been idle for 30 minutes, deletes expired salts and per-day deduplication data, and
   removes aggregates older than `retention_days`:

   ```cron
   * * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
   ```

3. **Country and visitor IP.** The country is read from the `CF-IPCountry` request header, so put the
   site (or the collector domain) behind Cloudflare with the proxy enabled, or another CDN that sends
   this header; without it every visit has the country `unknown`. Behind a proxy or CDN, configure
   Laravel's trusted proxies so that `$request->ip()` is the visitor's address: it is only used for the
   daily hash and rate limits, but if all requests seem to come from the proxy, all visitors become one.

Then set up one or both of the following.

## Single site: count the app itself

The site is the Laravel app the package is installed in. The script and the collector are served on
the app's own domain, the data is in its own database, and the statistics are in its own dashboard
or Filament admin; nothing is sent to another server.

1. Create the site while installing (or later with `stats:site create`):

   ```bash
   php artisan stats:install --site="Example" --domain=example.com    # add --force in production
   ```

2. Add the script to the `<head>` of your layout. `@statsScript` prints the tag of the site whose
   domains match the request (or of `STATS_HOST_SITE`, on its own domains); it prints nothing when
   there is no such site:

   ```blade
   @statsScript
   ```

3. Optionally count on the server as well (see [Counting on the server](#counting-on-the-server)):
   `TrackAiFetches` for AI fetchers, and `TrackPageviews` for page views without JavaScript and only
   for real pages. With `TrackPageviews`, keep the script for its events only:

   ```blade
   @statsScript(['data-pageviews' => 'off'])
   ```

4. Look at the statistics at `/stats`, or in your Filament admin with the [plugin](#filament) and
   `STATS_DASHBOARD_ENABLED=false`. With a single site there is no site switcher.

## Multiple sites: one app counts many websites

One Laravel app stores the statistics of other websites, which may be on other servers and do not
have to be Laravel apps. Their visitors' browsers send the data directly to this app.

1. **Choose where the collector lives.** The tracking script, the collector and the read API can run
   on their own domain, for example `stats.example.com`, while the dashboard stays on the app's
   normal domain:

   ```dotenv
   STATS_COLLECTOR_DOMAIN=stats.example.com
   ```

   Point that domain at the same Laravel app (DNS record, a web server `server` block with the same
   `root` and PHP handling, a TLS certificate). Leave it empty to serve them on the app's own domain
   under `STATS_COLLECTOR_PATH` (empty by default, so the script is at `/js/stats.js`).

2. **Add each website** with `stats:site create` (or on the dashboard) and put the printed snippet in
   the `<head>` of its pages:

   ```bash
   php artisan stats:site create --name="Example" --domain=example.com --domain=*.example.org --timezone=Europe/Berlin
   ```

   ```html
   <script defer data-site="01JC7…" src="https://stats.example.com/js/stats.js"></script>
   ```

   With `collector_domain` set, the snippet points to `https://` on that domain; otherwise its URL is
   built from `APP_URL`, so set `APP_URL` to the app's public URL first.

3. **Other Laravel sites** can also count on their own server (AI fetches, page views, events) and
   send it to this app in [client mode](#counting-on-the-server). Each such request crosses the
   network, so prefer the single-site setup for Laravel sites far from the host.

4. With more than one site, the dashboard (or the Filament page) has a site switcher.

## Site settings

Each counted website, in either setup, is a site with these settings (`stats:site create` and
`update`, or the Site settings page opened from the top right of the dashboard or of the Filament statistics page):

- **Domains** are the hostnames allowed to send data for the site. `example.com` also accepts
  `www.example.com`; `*.example.com` accepts `example.com` and every subdomain. Events from other
  hosts, from `localhost` and from IP addresses are dropped.
- **Testing locally**: the script sends nothing on `localhost` or an IP address. Open the page
  under a hostname that resolves to your machine, such as `127.0.0.1.nip.io`, and add that hostname
  to the site's domains.
- **Timezone** decides where a day starts and ends for the site's daily aggregates (default `UTC`).
- **Site languages** (`--locale=en --locale=zh-cn`, optional): the URL path prefixes of the site's
  language versions. Each page view is counted under its language version (the first path segment
  when it is one of them, otherwise `--default-locale`), so you see which language versions are read.
- **Excluded paths** (`--exclude-path=/admin*`, repeatable): paths that are never counted, for every
  way of sending (script and server); `*` matches any characters.
- **Public counts** (`--public-counts`, off by default): lets anyone read the all-time view and event
  counts of the site's pages without a token, see [Public counts](#public-counts).

Other actions:

```bash
php artisan stats:site list
php artisan stats:site update --site=<public id> --name="New name" --domain=example.com --timezone=UTC
php artisan stats:site delete --site=<public id>          # asks first; --force skips the question
php artisan stats:site key --site=<public id>             # server key for client mode, see below
```

`update` replaces the domain, site language and excluded path lists when any `--domain`, `--locale` or
`--exclude-path` is given. `delete` removes the site and all
of its statistics. Sites can also be added and edited on the dashboard.

## The tracking script

The script is a single dependency-free file of about 8 KB.

**Automatic:**

- A page view when the page loads, and another one each time the path changes through
  `history.pushState`, `history.replaceState` or `popstate`, so single-page apps are counted
  without extra code.
- An `outbound` event when a link to another host is clicked (left or middle button).
- A `download` event when a link to a file with one of the configured `download_extensions`
  (pdf, zip, dmg, exe, apk, …) is clicked.
- Time on page and scroll depth: when the page is hidden or left (closing the tab, switching tabs, a
  single-page-app navigation), the script reports how long the page was visible and how far it was
  scrolled. A page that is shown again reports only what was added.
- Outbound and download events carry the link and, when the link or one of its parents has a
  `data-stats-label` attribute, that label, e.g. the project a card belongs to:
  `<div data-stats-label="pairdock"><a href="https://github.com/…">…</a></div>`.

**Page details** make reports easier to read. Each page view sends the page title and, when present,
these meta tags. After a single-page-app navigation the page view is sent once the router has had a
chance to render the new page, so the new title and meta tags are the ones sent:

```html
<meta name="n2n-stats:type" content="article">   <!-- page type: [a-z0-9_-], up to 40 characters -->
<meta name="n2n-stats:group" content="post-42">  <!-- shared by the language versions of one piece of content -->
<meta name="n2n-stats:skip">                      <!-- e.g. on the 404 page: nothing is sent from this page -->
```

**Custom events**, either from JavaScript:

```js
window.n2nStats('signup', { label: 'pro-plan', target: '/checkout' });
```

or without JavaScript, by marking an element; a click sends the event, and when the element is or
is inside a link, the link (internal links too) is sent as its target:

```html
<a href="/checkout?plan=pro" data-stats-event="cta" data-stats-label="pro-plan">Buy</a>
```

Event names match `[a-z0-9_.-]{1,64}`; labels are at most 120 characters; targets are stored
without query string.

**Exclusions:** nothing is sent when `navigator.webdriver` is true (automated browsers), on
`file://` pages, or on localhost, `127.0.0.1` and `[::1]`. A `data-exclude-paths` attribute on the
script tag excludes paths: a comma-separated list where `*` matches any characters, including `/`
(for example `data-exclude-paths="/admin*,/preview/*"`). Downloads are detected by file extension;
`data-download-extensions="pdf,zip"` on the script tag replaces the built-in list. Requests whose User-Agent is
empty or matches the `bot_user_agents` list are dropped by the collector.

Data is sent with `navigator.sendBeacon` (or `fetch` with `keepalive` and no credentials) as a
`text/plain` request, so browsers do not send a CORS preflight. The collector always answers
`204 No Content`, also when it drops an event; only malformed requests get `400`.

## Counting on the server

Three things can be counted by your Laravel app itself instead of the browser:

- **AI fetches.** AI crawlers and assistants (GPTBot, ChatGPT-User, ClaudeBot, PerplexityBot, …) do
  not run JavaScript. The `TrackAiFetches` middleware records each matching GET request answered with
  2xx or 3xx as an `ai_fetch` event with the fetcher's name and the requested path. Fetchers listed in
  `ai_user_fetchers` (ChatGPT-User, Claude-User, Perplexity-User) are shown as user-triggered, the
  others as crawlers.
- **Page views, optionally.** The `TrackPageviews` middleware counts GET requests answered with 2xx
  and an HTML body (not prefetches, bots or the package's own pages). Visitors without JavaScript are
  counted, and 404 pages or made-up paths are not. Describe a page from your controller or view:

  ```php
  use N2ns\Stats\Stats;

  Stats::page(title: $post->title, type: 'article', group: 'post-'.$post->id);
  ```

  A site counted this way must **not** also send page views from the script, or every view is
  counted twice. To keep the script's outbound, download and custom events, load it with
  `data-pageviews="off"`:

  ```html
  <script defer data-site="01JC7…" data-pageviews="off" src="https://stats.example.com/js/stats.js"></script>
  ```
- **Events from the server**, e.g. when a controller redirects to a checkout:

  ```php
  Stats::event('checkout_start', label: 'pro', target: $checkoutUrl);
  ```

  The event is counted after the response, for the visitor of the current request, and is credited
  to that visitor's visit like events from the script.

- **On the host app**, the middleware and `Stats::event()` write to the database directly, through
  the same checks as the collector (site domains, rate limits, bot filter, excluded paths). Set
  `STATS_HOST_SITE` to the host's own site public id, so the site is not taken from the request's
  `Host` header. AI fetches are limited to `rate_limits.server_events_per_minute` per site and minute.
- **On another Laravel site**, install the same package in **client mode**. It creates no tables
  and only sends these events to the host (for page views and events together with the visitor's IP
  address and User-Agent, which the host needs for the daily visitor hash and does not store).
  Create a server key on the host:

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
use N2ns\Stats\Http\Middleware\TrackPageviews;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [TrackAiFetches::class, TrackPageviews::class]); // TrackPageviews only if wanted
})
```

In client mode everything is sent after the response has been delivered, with a 2-second timeout;
failures are logged and never affect the page.

## Dashboard

Open `/stats` (the `dashboard_path`) on your app's domain (unless `dashboard_enabled` is off, e.g. for
apps that use the [Filament](#filament) pages instead). It is plain server-rendered Blade with
inline SVG charts and a little plain JavaScript: no front-end build, no CDN, no external fonts. The
interface is in English and Simplified Chinese and follows your app's locale (`zh_CN` shows Chinese).
It looks like a default Filament panel (zinc grays, amber accent), has a light and a dark theme that
follow the system setting, works down to phone width, and respects reduced motion.

- Site switcher (with more than one site) and date ranges (today, yesterday, 7, 15, 30 and 90 days, this month, last month,
  custom). Changes are computed over complete days: a range that includes today compares its days up
  to yesterday with the same number of days before (today alone compares yesterday with the day
  before); the page shows both compared ranges.
- Overview: visitors, visits, page views, bounce rate and average visit duration as the main
  figures; pages viewed, views per visit, average time on page, events, outbound clicks and visitors
  online now (visits with activity in the last 5 minutes) below them. Each figure shows its change
  (a lower bounce rate counts as better) and a small trend line of its daily values. A
  daily chart shows one of visitors, visits, page views, events and outbound clicks at a time, with
  the same days before as a dashed line, each day's values on hover or with the arrow keys, and its
  data table.
- **Pages:** pages with title and type, entry pages with the page views, outbound clicks and events
  of the visits that started there, exit pages, time on page and scroll depth (average time and the
  share of page views that stayed at least 10 s, 30 s, 1 min, 3 min and scrolled 25%, 50%, 75%, to the
  end), page types, content groups, and moves from page to page; charts of the time on page and
  scroll depth of all pages, the content groups and the page flow (the most frequent moves).
- **Sources:** source types and sources (with page views, outbound clicks and events), UTM campaigns,
  and landing pages by source, by campaign and by search engine; charts of the daily visitors per
  source type and of the bounce rate of the sources with the most visits.
- **Visitors:** countries, devices, browsers, operating systems, browser languages and site languages,
  with visits, visitors and page views; a world map of the visitors per country.
- **Events:** custom events with label and target, outbound links and downloads with label, every
  event with the page it happened on, and events per site language version (for sites with
  site languages); charts of the daily count of the top custom events and of the custom events per
  page view of the pages with the most of them.
- **AI:** visits and visitors referred by AI assistants and AI fetches with a daily chart, AI referrals,
  the pages AI assistants send visitors to, AI fetchers (crawler or user-triggered, with the number of
  pages fetched, also as a chart) and the pages they fetched.
- Each report is a card with its main column, main figure and each row's share (reports of visits use
  their visitors, which imported days without visit data also have; a rate without visits shows –): the top 10 rows,
  with "show all" for the top 50. Reports with more columns have an "All columns" view with the full
  table. Tables can be sorted by any column and exported as CSV (up to 500 rows); source types,
  countries, devices, browsers, operating systems, site languages and page types also have a donut
  chart. The wide charts span the tab; the other charts and reports alternate between two columns
  that stack their cards (one column, in the same order, on narrow screens).
- Site management (Site settings, top right): add sites, edit domains, timezone, site languages,
  excluded paths and public counts, show and copy the embed snippet, reset the server key (shown once).

The pages are standalone by default. To show them inside your own Blade admin, with its menu around
them, set `dashboard_layout` to a layout to extend or to a layout component (there they keep the
light theme):

```php
// config/stats.php
'dashboard_layout' => ['view' => 'admin.layout', 'section' => 'content', 'title_sections' => ['title']],
// or: 'dashboard_layout' => ['component' => 'layouts.app'],   // page in its slot, title in its title attribute
'dashboard_back_url' => '/admin',   // a "Back" link at the top of the pages
```

Admins built with Inertia or another single-page front end can't wrap Blade pages; link to the
standalone pages from their menu, and set `dashboard_back_url` to come back.

## Filament

If your app uses [Filament](https://filamentphp.com), register the plugin in your panel:

```php
use N2ns\Stats\Filament\StatsPlugin;

$panel->plugin(StatsPlugin::make()->navigationGroup('Analytics')->navigationLabel('Statistics'));
```

It adds, built from native Filament components (the tab charts are the dashboard's inline SVG in
Filament sections) so they follow your panel's colours and dark mode:

- a statistics page: site (with more than one site) and range filters kept in the URL, the key figures with their change over
  complete days and daily sparklines, the daily traffic chart (the previous period's lines are in its
  legend, hidden until clicked), and the reports in tabs (pages, sources, visitors, events, AI) with
  the same charts as the dashboard, as sortable, searchable, paginated tables with CSV export and
  doughnut charts;
- a Sites resource to create, edit and delete sites (deleting removes their statistics too), copy
  the embed code and generate a server key for client mode, opened from the Site settings button at
  the top right of the statistics page rather than from the panel navigation; leave it out with
  `->sitesResource(false)`;
- widgets for your panel's dashboard with each site's visitors and page views for today and the last
  7 days (with the change over complete days) and the top 5 sources of the last 7 days.

All of it is visible only to users allowed by the `viewStats` gate. Report tabs load when they are
opened. The Filament pages do not use the standalone dashboard, so turn that off to keep a single entry:

```dotenv
STATS_DASHBOARD_ENABLED=false
```

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
| `GET /api/v1/sites/{public_id}/summary` | Totals for the range, and `comparison`: the complete-day current and previous ranges with their totals |
| `GET /api/v1/sites/{public_id}/timeseries` | Daily visitors, visits, page views, events, outbound clicks, pages viewed, views per visit, bounce rate, average visit duration and average time on page |
| `GET /api/v1/sites/{public_id}/ai` | AI visits, visitors, page views and fetches, with a daily series |
| `GET /api/v1/sites/{public_id}/breakdown/{report}?limit=&offset=&min_visitors=` | Rows of one report |
| `GET /api/v1/sites/{public_id}/snapshot?limit=&min_visitors=` | Summary, timeseries, AI and the top rows of every report in one call |

`report` is one of `pages`, `entries`, `exits`, `engagement`, `page_types`, `content`, `transitions`, `source_types`,
`sources`, `campaigns`, `landing_pages`, `campaign_pages`, `search_pages`, `countries`, `devices`,
`browsers`, `os`, `languages`, `locales`, `outbound`, `downloads`, `events`, `event_pages`, `event_locales`, `ai_referrals`,
`ai_pages`, `ai_fetchers`, `ai_fetched_pages`. Breakdown rows are paged with `limit` (1–500, default 50)
and `offset`; `has_more` says whether more rows follow. `min_visitors` leaves out rows with fewer
visitors. The snapshot's `limit` is 1–100 (default 10).

Every per-site endpoint takes the range as `from` and `to` (`YYYY-MM-DD` in the site's timezone; at
most 366 days; default the last 30 days) or as `period`, one of `today`, `yesterday`, `7d`, `15d`,
`30d`, `90d`, `month`, `last_month` (the same ranges as the dashboard). An unknown period, or a period
together with `from` or `to`, returns 422. Responses include `partial: true` when the range includes
the site's today, whose numbers are still growing. Requests are limited to 60 per minute per token.

```bash
curl -H "Authorization: Bearer $STATS_TOKEN" \
  "https://stats.example.com/api/v1/sites/01JC7…/summary?from=2026-11-01&to=2026-11-30"

curl -H "Authorization: Bearer $STATS_TOKEN" \
  "https://stats.example.com/api/v1/sites/01JC7…/breakdown/pages?from=2026-11-01&to=2026-11-30&limit=10"
```

## Public counts

To show view or like counts on your own pages, turn on public counts for the site
(`stats:site update --site=<public id> --public-counts`, or on the dashboard). Then anyone can read
all-time numbers without a token:

```bash
curl "https://stats.example.com/api/public/sites/01JC7…/counts?path[]=/blog/hello&event[]=like"
```

```json
{"site":"01JC7…","pages":{"/blog/hello":{"pageviews":1234,"last_date":"2026-11-10"}},"events":{"like":{"post-42":56}}}
```

Up to 50 paths and 20 custom event names per request; event counts are grouped by label. Responses
allow any origin (`Access-Control-Allow-Origin: *`), are cacheable for 60 seconds and are limited to
`rate_limits.public_per_minute` requests per IP and minute. Sites without public counts answer 404.

## Configuration

Publish the file with `php artisan vendor:publish --tag=stats-config`. Main options:

| Key | Env | Default | Meaning |
| --- | --- | --- | --- |
| `mode` | `STATS_MODE` | `host` | `host` stores data and serves the collector, dashboard and API; `client` only sends server-side events (AI fetches, page views, events) to a host |
| `table_prefix` | `STATS_TABLE_PREFIX` | `stats_` | Prefix of all tables |
| `collector_domain` | `STATS_COLLECTOR_DOMAIN` | none | Domain for the script, collector and API; none = the current domain |
| `collector_path` | `STATS_COLLECTOR_PATH` | empty | Path prefix of those routes |
| `dashboard_enabled` | `STATS_DASHBOARD_ENABLED` | `true` | Serve the standalone dashboard and site management pages; turn off when the host shows the statistics in its own admin (Filament plugin) |
| `dashboard_path` | `STATS_DASHBOARD_PATH` | `stats` | Dashboard route prefix |
| `dashboard_middleware` | | `['web']` | Middleware of the dashboard routes (the `viewStats` gate is always applied) |
| `dashboard_layout` | | `null` | Host admin layout for the dashboard and site pages: `['view' => …, 'section' => …, 'title_sections' => […]]` or `['component' => …]`; standalone when null |
| `dashboard_back_url` | `STATS_DASHBOARD_BACK_URL` | `null` | Address of a back link at the top of the dashboard and site pages |
| `dashboard_back_label` | | `null` | Text of the back link (the translated "Back" when null) |
| `host_site` | `STATS_HOST_SITE` | none | Host mode: public id of the app's own site, used by the middleware, `Stats::event()` and `@statsScript` (which prints a tag only on that site's domains); none = found by the `Host` header |
| `visit_timeout_minutes` | | `30` | Idle time that ends a visit |
| `retention_days` | `STATS_RETENTION_DAYS` | none | Days to keep daily aggregates; none = forever |
| `rate_limits` | | | Collector limits per site (3000/min), per IP (120/min, IPv6 per /64) and per visitor (60/min); AI fetches per site (600/min), API per token (60/min), public counts per IP (120/min) |
| `max_new_rows_per_day` | | `5000` | New daily rows per site, day and table; further values are counted under `(other)` |
| `bot_user_agents` | | | User-Agent substrings that are dropped as bots |
| `ai_fetchers` | | | User-Agent substrings counted as AI fetchers |
| `ai_user_fetchers` | | | AI fetchers that fetch on a person's request (the others are crawlers) |
| `search_hosts`, `social_hosts`, `ai_hosts` | | | Referrer hosts that classify a source as search, social or AI; `ai_hosts` also match a `utm_source` given without medium and campaign |
| `click_ids` | | | Click-id query parameters (`gclid`, `fbclid`, …) and the source they imply |
| `download_extensions` | | | File extensions counted as downloads |
| `client.endpoint`, `client.site`, `client.key` | `STATS_CLIENT_*` | | Client mode: host URL, site public id and server key |

## Data model

All tables use the `table_prefix`. Daily tables are bucketed by the site's timezone.

| Table | Contents | Kept |
| --- | --- | --- |
| `stats_sites` | Name, public id, allowed domains, timezone, site languages, excluded paths, public counts, hash of the server key | Until deleted |
| `stats_api_tokens` | Name, hash of the token, allowed sites, last use | Until revoked |
| `stats_daily_totals` | Page views, visitors, visits, bounces, visit duration, events, outbound clicks per day | `retention_days` |
| `stats_daily_pages` | Per path: page views, visitors, entries, visitors who entered there, exits, the page views, outbound clicks and events of visits that entered there; time on page and scroll depth; title, page type, content group | `retention_days` |
| `stats_daily_sources` | Per source (type, host or UTM source, medium, campaign, content, term): visits, visitors, page views, bounces, outbound clicks, events | `retention_days` |
| `stats_daily_entries` | Per entry page and source: visits, visitors | `retention_days` |
| `stats_daily_transitions` | Per move from one page to another: count, visitors | `retention_days` |
| `stats_daily_dimensions` | Per country, device, browser, OS, browser language, site language: visits, visitors, page views | `retention_days` |
| `stats_daily_events` | Outbound clicks, downloads, custom events and AI fetches per name, label, target and page: count, visitors | `retention_days` |
| `stats_salts` | The random salt of each day | Two days |
| `stats_uniques` | Visitor hashes already counted per day and report row | Two days |
| `stats_visits` | Visits in progress (hash, start, last activity, page views, entry and exit page, source, device class, browser, OS, country, language) | Until 30 minutes idle |

## How the metrics are defined

- **Page view:** one per page load or single-page-app path change reported by the script, or per
  successful HTML response counted by the `TrackPageviews` middleware.
- **Pages viewed:** the number of different paths with at least one page view in the range.
- **Visitor:** a unique `sha256(daily salt + site + IP + User-Agent)` within one day. The salt
  changes daily, so the same person is a new visitor every day. **Visitors over a range of days are
  the sum of the daily unique visitors**, not a count of distinct people.
- **Visit:** page views and events from the same visitor with less than 30 minutes between them.
  After 30 minutes of inactivity the next page view starts a new visit. When the daily salt
  changes, the visitor hash changes too, so the next page view also starts a new visit.
- **Source:** decided when a visit starts, from UTM parameters, the referrer and click ids
  (in that order: an AI assistant's own `utm_source` alone, such as ChatGPT's `utm_source=chatgpt.com`;
  UTM; AI, search, social, click id, other referrer; direct). Referrals from the site itself count as
  direct.
- **Views per visit:** page views of the visits that ended / visits that ended, so page views without
  visit data (imported history) are left out.
- **Bounce:** a visit that ended with exactly one page view. Bounce rate = bounces / visits; a source
  without visits (such as imported history) has no bounce rate (`null` in the API).
- **Events:** outbound clicks, downloads and custom events (AI fetches are counted separately). An
  event is credited to the source and the entry page of the visit it happens in.
- **Page move:** two consecutive page views of one visit on different paths.
- **Time on page:** how long a page was visible (time in a hidden tab does not count), reported by the
  script when the page is hidden or left; at most 30 minutes per page view. Pages viewed without the
  script reporting it (closed before the report could be sent, or counted only on the server without
  the script) are not part of these figures; "measured views" says how many page views they cover.
- **Scroll depth:** the deepest point of the page that was in view, as a percentage of the page
  height; a page that fits on the screen counts as 100%. A page that was never visible (opened in a
  background tab and closed unseen) reports nothing.
- **Visit duration** includes the time on the last page when the script reported it.
- **Visit duration:** the time between the first and the last activity of a visit; the total of
  ended visits is stored, and the average is derived from it. A single page view without further
  events lasts 0 seconds.
- Bounces, durations, exit pages, page views per country, device, browser, OS and browser language,
  and the page views after an entry page are counted when a visit ends (30 minutes after its last
  activity), so the most recent figures fill in with a delay.
- **Comparisons** use complete days only (see [Dashboard](#dashboard)).

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
- With the script alone, visitors without JavaScript are not counted, and a page that loads the
  script is counted whatever its HTTP status unless it carries `<meta name="n2n-stats:skip">`. Use the
  `TrackPageviews` middleware for server-side page views. Crawlers other than AI fetchers are never counted.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Please report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md).

## License

[Apache-2.0](LICENSE). Copyright 2026 Datafrog LLC.
