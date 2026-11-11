# Privacy

N2N Stats is built so that a website can measure its traffic without cookies and without keeping
personal data. This page lists exactly what it processes and stores. It describes the software;
it is not legal advice, and each site owner remains responsible for their own privacy policy.

## What the browser sends

For each page view or event, the tracking script sends one request with:

- the site's public id and the event type (page view, outbound click, download, custom event);
- the current page URL and, for page views, `document.referrer`, the page title and the page type
  and content group the site declares in meta tags;
- for custom events, the event name, an optional label and an optional target link; for outbound
  clicks and downloads, the link target and an optional label set by the site;
- the screen width in pixels.

The request itself also carries the visitor's IP address, User-Agent, `Accept-Language` header
and, behind Cloudflare, the `CF-IPCountry` header.

Sites can instead count page views and some events on their own server. Then the same
information comes from the request to the site itself; when that site is not the one that stores the
statistics, its server forwards the page URL, referrer, IP address, User-Agent, `Accept-Language`
and country header to the statistics server of the same owner.

The script does not set or read cookies and does not use `localStorage`, `sessionStorage` or any
other browser storage. It sends nothing in automated browsers (`navigator.webdriver`).

## What is processed and immediately discarded

- **IP address and User-Agent:** combined with a random daily salt and the site into a hash,
  `sha256(daily salt + site + IP + User-Agent)`. The User-Agent is also used to derive the device
  class, browser and operating system. Neither is stored. Rate limits are keyed by an HMAC of the date and the IP address (IPv6 per /64)
  with the application key, which cannot be turned back into an IP address without that key and
  changes every day; the cache entries expire after a minute.
- **Full URLs:** the script sends only the path and the UTM and click-id parameters, never the
  fragment or other query parameters. Only the path is kept. Before that, the
  UTM parameters are read (their values are stored with the source) and click-id parameters such as
  `gclid` are checked to classify the source (their values are not stored).
- **Referrer:** only the host (or the UTM source) is kept.
- **Screen width:** used only to tell tablets from phones.

## What is stored temporarily

| Data | Purpose | Deleted |
| --- | --- | --- |
| A random salt per day | Computing the day's visitor hashes | Within 48 hours after the day ends |
| Visitor hashes per day and report row | Counting each visitor once per day and row | Within 48 hours after the day ends |
| Visits in progress (visitor hash, start and last activity time, page view count, entry and exit page, source, country, device class, browser, operating system, language) | Counting visits, bounces, visit duration and page views per country, device and so on | After 30 minutes without activity |

Once a day's salt is deleted, its hashes can no longer be linked to an IP address and User-Agent,
even by the site owner. Because the salt changes daily and the site is part of the hash, the same
person cannot be linked across days or across sites.

## What is stored permanently

Only daily counts per site:

- totals: page views, visitors, visits, bounces, total visit duration, events, outbound clicks;
- per page path: page views, visitors, entries, exits, the page views, outbound clicks and events of
  visits that started there, and the page's title, type and content group;
- per source: source type, referrer host or UTM source, UTM medium, campaign, content and term;
  with visits, visitors, page views, bounces, outbound clicks and events;
- per entry page and source: visits and visitors;
- per move from one page path to another within a visit: the count and the number of visitors;
- per country (two-letter code from the CDN header, or `unknown`), device class, browser,
  operating system, language (primary tag of `Accept-Language`, such as `en`) and the site's
  language version of the page: visits, visitors and page views;
- per outbound link, download, custom event and AI fetcher: the name, the label, the target without
  its query string, the page path, the count and the number of visitors.

By default these aggregates are kept forever. The site operator can set `retention_days` to
delete them after a number of days.

AI fetchers counted by the server-side middleware are recorded by fetcher name and path only;
they have no visitor.

## Where the data lives

All data is stored in the database of the site owner's own Laravel application. Nothing is sent
to Datafrog LLC or any other third party, and no IP address is sent to a geolocation service.
In client mode, a Laravel site sends AI fetch events (fetcher name and path) and, if it counts
page views or events on the server, the details listed above only to the host configured by its
owner, which uses the IP address and User-Agent only to compute the hash.

If the owner turns on public counts for a site, the all-time page view counts of requested paths and
the counts of requested custom events can be read by anyone; they contain no visitor data.

## What site owners should check

- Do not put personal data in page paths, page titles, page types, content groups, event names,
  event labels, link targets or UTM parameters; these are stored as they are (link targets without
  their query string).

## Example paragraph for a privacy policy

Adapt this to your setup (for example, remove the country sentence if you do not use Cloudflare):

> We use N2N Stats, a self-hosted analytics tool, to count visits to this website. It does not use
> cookies or store anything in your browser. When you open a page, your IP address and browser
> identification are combined with a random value that changes every day into a one-way hash, which
> lets us count unique visitors per day; neither your IP address nor your browser identification is
> stored, and the daily value is deleted within two days, so you cannot be recognised on another day
> or on other websites. Your country is taken from a header added by our CDN, Cloudflare. We keep
> only daily totals, such as how many people visited a page, which website or campaign referred
> them, and their country, device type, browser, operating system and language. The data is stored
> on our own servers and is not shared with third parties.
