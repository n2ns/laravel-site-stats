<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title>@yield('title') · N2N Stats</title>
<style>
:root{--bg:#f6f7f9;--panel:#fff;--text:#1b1f24;--muted:#5d6672;--border:#dde1e6;--accent:#2563eb;--accent2:#d9480f;--good:#1a7f37;--bad:#c62828;--hover:#f0f3f7;--bar:rgba(37,99,235,.10)}
@media (prefers-color-scheme:dark){:root{--bg:#0f1216;--panel:#171b21;--text:#e6e9ee;--muted:#9aa4b1;--border:#2a3038;--accent:#6ea0ff;--accent2:#ff8a5b;--good:#4cc472;--bad:#ff6b6b;--hover:#1e242c;--bar:rgba(110,160,255,.14)}}
*{box-sizing:border-box}
[hidden]{display:none!important}
body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:var(--accent)}
.wrap{max-width:1200px;margin:0 auto;padding:16px}
header.top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
header.top .brand{font-weight:700;font-size:16px;text-decoration:none;color:var(--text)}
nav.main a{margin-left:12px}
.panel{background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px}
.toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:end}
.toolbar label{display:flex;flex-direction:column;font-size:12px;color:var(--muted);gap:2px}
input,select,textarea,button{font:inherit;color:var(--text);background:var(--panel);border:1px solid var(--border);border-radius:6px;padding:6px 8px}
textarea{width:100%;min-height:80px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
button,.button{cursor:pointer;background:var(--accent);border-color:var(--accent);color:#fff;text-decoration:none;display:inline-block;padding:6px 12px;border-radius:6px}
button.secondary{background:transparent;color:var(--accent)}
:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.muted{color:var(--muted)}
.error{color:var(--bad)}
.notice{border-left:4px solid var(--accent)}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.kpi{background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:12px}
.kpi .label{font-size:12px;color:var(--muted)}
.kpi .value{font-size:24px;font-weight:650;font-variant-numeric:tabular-nums}
.kpi .delta{font-size:12px}
.up{color:var(--good)}.down{color:var(--bad)}
.chart svg{width:100%;height:auto;display:block}
.chart .grid{stroke:var(--border)}
.chart .axis{fill:var(--muted);font-size:11px}
.chart .l-visitors{stroke:var(--accent);fill:none;stroke-width:2}
.chart .l-pageviews{stroke:var(--accent2);fill:none;stroke-width:2}
.chart .p-visitors{fill:var(--accent)}.chart .p-pageviews{fill:var(--accent2)}
.legend{display:flex;gap:16px;font-size:12px;color:var(--muted)}
.legend span::before{content:"";display:inline-block;width:10px;height:3px;margin-right:6px;vertical-align:middle;background:currentColor}
.legend .visitors{color:var(--accent)}.legend .pageviews{color:var(--accent2)}
.tabs{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid var(--border);margin-bottom:12px}
.tabs button{background:transparent;color:var(--muted);border:0;border-bottom:2px solid transparent;border-radius:0}
.tabs button[aria-selected=true]{color:var(--text);border-bottom-color:var(--accent)}
.reports{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px}
.report h3{margin:0 0 8px;font-size:14px;display:flex;justify-content:space-between;gap:8px}
.report h3 a{font-weight:400;font-size:12px}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums}
th,td{padding:5px 6px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
th{font-size:12px;color:var(--muted);font-weight:600}
td.num,th.num{text-align:right;white-space:nowrap}
td.key{word-break:break-all}
tbody tr:hover{background:var(--hover)}
code,pre{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
pre{background:var(--hover);padding:8px;border-radius:6px;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
footer{font-size:12px;color:var(--muted);margin:24px 0}
.site-form{display:grid;gap:8px;max-width:640px}
.site-form label{display:flex;flex-direction:column;gap:2px;font-size:12px;color:var(--muted)}
details>summary{cursor:pointer;font-weight:600}
@media (max-width:600px){.reports{grid-template-columns:1fr}.kpi .value{font-size:20px}}
</style>
</head>
<body>
<div class="wrap">
<header class="top">
<a class="brand" href="{{ route('stats.dashboard') }}">N2N Stats</a>
<nav class="main" aria-label="Main"><a href="{{ route('stats.dashboard') }}">Dashboard</a><a href="{{ route('stats.sites.index') }}">Sites</a></nav>
</header>
<main>
@yield('content')
</main>
<footer>
Figures are for reference only. N2N Stats sets no cookies and uses no browser storage; it does not store IP addresses or User-Agents.
Visitors are counted once per day with a daily random salt, so the same person is not recognised across days or sites:
“visitors” over a range is the sum of daily unique visitors. Bounce rate and visit duration count finished visits.
</footer>
</div>
@stack('scripts')
</body>
</html>
