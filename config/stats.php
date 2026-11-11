<?php

return [

    /*
    | 'host' stores data and serves the collector, dashboard and API.
    | 'client' only sends server-side events (AI fetches, page views, events) to a host and creates no tables.
    */
    'mode' => env('STATS_MODE', 'host'),

    'table_prefix' => env('STATS_TABLE_PREFIX', 'stats_'),

    /*
    | Domain that serves the tracking script, the collector and the read API
    | (e.g. stats.example.com). Null serves them on the current domain under collector_path.
    */
    'collector_domain' => env('STATS_COLLECTOR_DOMAIN'),
    'collector_path' => env('STATS_COLLECTOR_PATH', ''),

    // Dashboard route prefix on the host app's web domain, guarded by the viewStats gate.
    'dashboard_path' => env('STATS_DASHBOARD_PATH', 'stats'),
    'dashboard_middleware' => ['web'],

    /*
    | Host mode: public id of the site the TrackAiFetches and TrackPageviews middleware and Stats::event() count for. When null,
    | the site is found by the request's Host header (only safe when the web server rejects unknown hosts).
    */
    'host_site' => env('STATS_HOST_SITE'),

    'visit_timeout_minutes' => 30,

    // Days to keep daily aggregates; null keeps them forever.
    'retention_days' => env('STATS_RETENTION_DAYS'),

    'rate_limits' => [
        'per_site_per_minute' => 3000,
        'per_ip_per_minute' => 120, // IPv6 counted per /64
        'per_visitor_per_minute' => 60,
        'server_events_per_minute' => 600,
        'api_per_minute' => 60,
        'public_per_minute' => 120, // public counts endpoint, per IP (IPv6 per /64)
    ],

    'bot_user_agents' => [
        'bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit', 'headless', 'monitor',
        'curl', 'wget', 'python-requests', 'google-inspectiontool', 'lighthouse', 'googleother',
        'google-extended', 'mediapartners-google', 'apis-google', 'feedfetcher', 'google-read-aloud',
        'go-http-client', 'axios', 'node-fetch', 'okhttp', 'java/', 'scrapy', 'python-urllib',
        'python-httpx', 'aiohttp', 'libwww-perl', 'httpclient', 'guzzlehttp', 'postmanruntime',
        'phantomjs', 'puppeteer', 'playwright', 'selenium',
    ],

    // User-Agent substrings of AI fetchers; the matching entry becomes the event name.
    'ai_fetchers' => [
        'oai-searchbot', 'oai-adsbot', 'gptbot', 'chatgpt-user', 'claudebot', 'claude-user',
        'claude-searchbot', 'perplexitybot', 'perplexity-user',
    ],

    // AI fetchers that fetch a page because a person asked the assistant about it (the others are crawlers).
    'ai_user_fetchers' => ['chatgpt-user', 'claude-user', 'perplexity-user'],

    // Referrer host substrings.
    'search_hosts' => ['google.', 'bing.com', 'duckduckgo.com', 'yahoo.', 'yandex.', 'baidu.', 'naver.', 'sogou.'],
    'social_hosts' => [
        'facebook.com', 'instagram.com', 'linkedin.com', 'lnkd.in', 'reddit.com', 't.co', 'twitter.com',
        'x.com', 'youtube.com', 'tiktok.com',
    ],
    // Exact referrer hosts.
    'ai_hosts' => ['chatgpt.com', 'chat.openai.com', 'claude.ai', 'gemini.google.com', 'perplexity.ai', 'copilot.microsoft.com'],

    // Query parameter => [source_type, source]
    'click_ids' => [
        'fbclid' => ['social', 'meta.com'],
        'gclid' => ['search', 'google.com'],
        'gbraid' => ['search', 'google.com'],
        'wbraid' => ['search', 'google.com'],
        'dclid' => ['search', 'google.com'],
        'msclkid' => ['search', 'bing.com'],
        'ttclid' => ['social', 'tiktok.com'],
        'li_fat_id' => ['social', 'linkedin.com'],
        'twclid' => ['social', 'x.com'],
    ],

    /*
    | New daily rows each site may create per day and table (pages, sources, events, dimensions).
    | Beyond it, values are counted under '(other)', so forged values cannot grow the tables without bound.
    */
    'max_new_rows_per_day' => 5000,

    'download_extensions' => [
        'pdf', 'zip', 'gz', 'tgz', '7z', 'rar', 'dmg', 'pkg', 'exe', 'msi', 'apk', 'aab', 'ipa', 'deb', 'rpm',
        'appimage', 'vsix', 'xlsx', 'xls', 'docx', 'doc', 'pptx', 'csv', 'txt', 'mp3', 'mp4', 'mov', 'iso',
    ],

    'client' => [
        'endpoint' => env('STATS_CLIENT_ENDPOINT'),
        'site' => env('STATS_CLIENT_SITE'),
        'key' => env('STATS_CLIENT_KEY'),
        'timeout_seconds' => 2,
    ],
];
