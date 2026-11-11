<style>
/* Design tokens: light theme by default, dark theme from prefers-color-scheme (and Filament's .dark class). */
.stats-dash{
  color-scheme:light;
  --bg:#f5f6f8;--surface:#ffffff;--surface-2:#f0f2f5;--text:#14181f;--text-2:#454c58;--muted:#5d6470;
  --border:#e1e4e9;--border-strong:#c9ced6;--accent:#4338ca;--accent-hover:#3730a3;--on-accent:#ffffff;
  --accent-soft:rgba(67,56,202,.10);--bar:rgba(67,56,202,.12);--area:rgba(67,56,202,.10);
  --good:#15803d;--bad:#b91c1c;--flat:#5d6470;--grid:#e8eaee;
  --s0:#2a78d6;--s1:#eb6834;--s2:#1baf7a;--s3:#eda100;--s4:#e87ba4;--s-other:#a3a9b3;
  --shadow:0 1px 2px rgba(20,24,31,.05),0 1px 3px rgba(20,24,31,.04);
  --radius:12px;--radius-sm:8px;
  --space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:24px;--space-6:32px;
  --fs-xs:12px;--fs-sm:13px;--fs-md:15px;--fs-lg:18px;--fs-xl:22px;--fs-2xl:28px;
  --font:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","PingFang SC","Microsoft YaHei",sans-serif;
  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  color:var(--text);font:var(--fs-md)/1.5 var(--font);-webkit-text-size-adjust:100%;
}
@media (prefers-color-scheme:dark){.stats-dash:not(.stats-filament){
  color-scheme:dark;
  --bg:#0e1014;--surface:#171a20;--surface-2:#1f232b;--text:#eceef2;--text-2:#c0c5ce;--muted:#9ba2ad;
  --border:#2a2f38;--border-strong:#3b414c;--accent:#9aa0ff;--accent-hover:#b4b8ff;--on-accent:#12141a;
  --accent-soft:rgba(154,160,255,.14);--bar:rgba(154,160,255,.16);--area:rgba(154,160,255,.14);
  --good:#4ade80;--bad:#f87171;--flat:#9ba2ad;--grid:#252a32;
  --s0:#3987e5;--s1:#d95926;--s2:#199e70;--s3:#c98500;--s4:#d55181;--s-other:#5b626d;
  --shadow:none;
}}
.dark .stats-dash.stats-filament{
  color-scheme:dark;
  --bg:#0e1014;--surface:#171a20;--surface-2:#1f232b;--text:#eceef2;--text-2:#c0c5ce;--muted:#9ba2ad;
  --border:#2a2f38;--border-strong:#3b414c;--accent:#9aa0ff;--accent-hover:#b4b8ff;--on-accent:#12141a;
  --accent-soft:rgba(154,160,255,.14);--bar:rgba(154,160,255,.16);--area:rgba(154,160,255,.14);
  --good:#4ade80;--bad:#f87171;--flat:#9ba2ad;--grid:#252a32;
  --s0:#3987e5;--s1:#d95926;--s2:#199e70;--s3:#c98500;--s4:#d55181;--s-other:#5b626d;
  --shadow:none;
}
.stats-dash *,.stats-dash *::before,.stats-dash *::after{box-sizing:border-box}
.stats-dash [hidden]{display:none!important}
.stats-dash a{color:var(--accent);text-decoration-thickness:1px;text-underline-offset:2px}
.stats-dash a:hover{color:var(--accent-hover)}
.stats-dash h1,.stats-dash h2,.stats-dash h3{font-weight:650;line-height:1.3;margin:0}
.stats-dash :focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:4px}
.stats-dash .muted{color:var(--muted)}
.stats-dash .error{color:var(--bad)}
.stats-dash .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.stats-dash code,.stats-dash pre{font-family:var(--mono);font-size:var(--fs-xs)}
.stats-dash pre{margin:0;background:var(--surface-2);border:1px solid var(--border);padding:var(--space-3);border-radius:var(--radius-sm);overflow-x:auto;white-space:pre-wrap;word-break:break-all}

/* Controls */
.stats-dash input,.stats-dash select,.stats-dash textarea,.stats-dash button{font:inherit;font-size:var(--fs-sm);color:var(--text);background:var(--surface);border:1px solid var(--border-strong);border-radius:var(--radius-sm);padding:7px 10px;min-height:36px;max-width:100%}
.stats-dash select{padding-right:28px}
.stats-dash textarea{width:100%;min-height:84px;font-family:var(--mono);font-size:var(--fs-xs);resize:vertical}
.stats-dash input:hover,.stats-dash select:hover,.stats-dash textarea:hover{border-color:var(--muted)}
.stats-dash [aria-invalid=true]{border-color:var(--bad)}
.stats-dash .button{font-size:var(--fs-sm);border:1px solid;border-radius:var(--radius-sm);padding:7px 12px;min-height:36px}
.stats-dash button,.stats-dash .button{cursor:pointer;background:var(--accent);border-color:var(--accent);color:var(--on-accent);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.stats-dash button:hover,.stats-dash .button:hover{background:var(--accent-hover);border-color:var(--accent-hover);color:var(--on-accent)}
.stats-dash button.secondary,.stats-dash .button.secondary{background:var(--surface);color:var(--text);border-color:var(--border-strong)}
.stats-dash button.secondary:hover,.stats-dash .button.secondary:hover{background:var(--surface-2);color:var(--text)}
.stats-dash button.link{background:none;border:0;color:var(--accent);padding:4px 0;min-height:0;font-weight:500}
.stats-dash button.link:hover{text-decoration:underline;background:none;color:var(--accent-hover)}

/* Cards */
.stats-dash .card,.stats-dash .panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:var(--space-4) var(--space-5);min-width:0}
.stats-dash .card-head{display:flex;align-items:baseline;justify-content:space-between;gap:var(--space-3);margin-bottom:var(--space-3)}
.stats-dash .card-head h2{font-size:var(--fs-lg)}
.stats-dash .card-head h3{font-size:var(--fs-md)}
.stats-dash .card-head a{font-size:var(--fs-xs);font-weight:500;white-space:nowrap}
.stats-dash .notice{border-left:4px solid var(--accent)}
.stats-dash .empty{color:var(--muted);font-size:var(--fs-sm);margin:var(--space-5) 0;text-align:center}

/* Toolbar */
.stats-dash .toolbar{display:flex;flex-wrap:wrap;gap:var(--space-3);align-items:flex-end;justify-content:space-between;margin-bottom:var(--space-2)}
.stats-dash .toolbar form{display:flex;flex-wrap:wrap;gap:var(--space-2);align-items:flex-end;margin:0;min-width:0}
.stats-dash .toolbar label{display:flex;flex-direction:column;gap:2px;font-size:var(--fs-xs);color:var(--muted);min-width:0}
.stats-dash .site-switch select{font-size:var(--fs-lg);font-weight:650;border-color:transparent;background:transparent;padding:4px 32px 4px 6px;margin-left:-6px;max-width:100%}
.stats-dash .site-switch select:hover{border-color:var(--border-strong)}
.stats-dash .site-switch .site-name{display:block;font-size:var(--fs-lg);font-weight:650;padding:4px 0}
.stats-dash .range-form .custom{display:flex;flex-wrap:wrap;gap:var(--space-2)}
.stats-dash .period{font-size:var(--fs-sm);color:var(--muted);margin:0 0 var(--space-5)}
.stats-dash .period strong{color:var(--text-2);font-weight:600}
.stats-dash .period p{margin:0}
.stats-dash .alert{color:var(--bad);border:1px solid var(--bad);border-radius:var(--radius-sm);padding:var(--space-2) var(--space-3);margin:var(--space-2) 0 0}

/* KPI strip */
.stats-dash .kpis{display:grid;gap:var(--space-3);margin-bottom:var(--space-3)}
.stats-dash .kpis.primary{grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr))}
.stats-dash .kpis.secondary{grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr));margin-bottom:var(--space-5)}
.stats-dash .kpi{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:var(--space-3) var(--space-4);min-width:0;display:flex;flex-direction:column;gap:2px}
.stats-dash .kpi .label{font-size:var(--fs-xs);font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.stats-dash .kpi .value{font-size:var(--fs-2xl);font-weight:700;line-height:1.2;font-variant-numeric:tabular-nums;letter-spacing:-.01em}
.stats-dash .kpis.secondary .kpi{padding:var(--space-2) var(--space-3);box-shadow:none;background:var(--surface)}
.stats-dash .kpis.secondary .label{text-transform:none;letter-spacing:0;font-weight:500}
.stats-dash .kpis.secondary .value{font-size:var(--fs-lg);font-weight:650}
.stats-dash .kpi .foot{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:var(--space-2);min-height:22px;min-width:0}
.stats-dash .delta{font-size:var(--fs-xs);font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap}
.stats-dash .delta .vs{font-weight:400;color:var(--muted)}
.stats-dash .delta.good{color:var(--good)}.stats-dash .delta.bad{color:var(--bad)}.stats-dash .delta.flat{color:var(--flat)}
.stats-dash .note{font-size:var(--fs-xs);color:var(--muted)}
.stats-dash svg.spark{width:72px;height:24px;flex:none;overflow:visible}
.stats-dash svg.spark path{fill:none;stroke:var(--accent);stroke-width:1.5;stroke-linejoin:round;stroke-linecap:round;opacity:.85}
.stats-dash .kpi .live{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--good);margin-right:6px;vertical-align:middle}

/* Line chart */
.stats-dash .chart{margin-bottom:var(--space-5)}
.stats-dash .chart .card-head{flex-wrap:wrap;align-items:center}
.stats-dash .metric-radio{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
.stats-dash .metric-tabs{display:flex;flex-wrap:wrap;gap:2px;background:var(--surface-2);border-radius:var(--radius-sm);padding:3px}
.stats-dash .metric-tabs label{font-size:var(--fs-sm);padding:4px 10px;border-radius:6px;color:var(--text-2);cursor:pointer;white-space:nowrap}
.stats-dash .metric-tabs label:hover{color:var(--text)}
.stats-dash .plot-wrap{display:grid;grid-template-columns:auto minmax(0,1fr);column-gap:var(--space-2);margin-top:var(--space-2)}
.stats-dash .y-axis{position:relative;height:240px;font-size:11px;color:var(--muted);font-variant-numeric:tabular-nums;text-align:right;min-width:2.5em}
.stats-dash .y-axis span{position:absolute;right:0;transform:translateY(-50%);white-space:nowrap}
.stats-dash .plot{position:relative;height:240px;border-radius:4px;touch-action:pan-y}
.stats-dash .plot svg{display:block;width:100%;height:100%;overflow:visible}
.stats-dash .plot .grid{stroke:var(--grid);stroke-width:1}
.stats-dash .plot .baseline{stroke:var(--border-strong);stroke-width:1}
.stats-dash .plot .area{fill:var(--area);stroke:none}
.stats-dash .plot .line{fill:none;stroke:var(--accent);stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
.stats-dash .x-axis{grid-column:2;position:relative;height:22px;font-size:11px;color:var(--muted);margin-top:4px}
.stats-dash .x-axis span{position:absolute;top:0;white-space:nowrap}
.stats-dash .x-axis .middle{transform:translateX(-50%)}.stats-dash .x-axis .end{transform:translateX(-100%)}
.stats-dash .layer{display:none}
.stats-dash .m0:checked~.plot-wrap .l0,.stats-dash .m1:checked~.plot-wrap .l1,.stats-dash .m2:checked~.plot-wrap .l2,
.stats-dash .m3:checked~.plot-wrap .l3,.stats-dash .m4:checked~.plot-wrap .l4,.stats-dash .m5:checked~.plot-wrap .l5{display:inline}
.stats-dash .m0:checked~.card-head .t0,.stats-dash .m1:checked~.card-head .t1,.stats-dash .m2:checked~.card-head .t2,
.stats-dash .m3:checked~.card-head .t3,.stats-dash .m4:checked~.card-head .t4,.stats-dash .m5:checked~.card-head .t5{background:var(--surface);color:var(--text);font-weight:600;box-shadow:var(--shadow);outline:1px solid var(--border)}
.stats-dash .m0:focus-visible~.card-head .t0,.stats-dash .m1:focus-visible~.card-head .t1,.stats-dash .m2:focus-visible~.card-head .t2,
.stats-dash .m3:focus-visible~.card-head .t3,.stats-dash .m4:focus-visible~.card-head .t4,.stats-dash .m5:focus-visible~.card-head .t5{outline:2px solid var(--accent);outline-offset:1px}
.stats-dash .guide{position:absolute;top:0;bottom:0;width:0;border-left:1px dashed var(--border-strong);pointer-events:none}
.stats-dash .dot{position:absolute;width:10px;height:10px;margin:-5px 0 0 -5px;border-radius:50%;background:var(--accent);border:2px solid var(--surface);pointer-events:none}
.stats-dash .tip{position:absolute;top:8px;z-index:2;min-width:150px;max-width:240px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:var(--radius-sm);box-shadow:0 4px 16px rgba(0,0,0,.14);padding:var(--space-2) var(--space-3);font-size:var(--fs-xs);pointer-events:none}
.stats-dash .tip .day{font-weight:600;margin-bottom:4px;color:var(--text-2)}
.stats-dash .tip dl{display:grid;grid-template-columns:1fr auto;gap:2px var(--space-3);margin:0}
.stats-dash .tip dt{color:var(--muted)}.stats-dash .tip dd{margin:0;text-align:right;font-variant-numeric:tabular-nums}
.stats-dash .tip .on{color:var(--text);font-weight:650}
.stats-dash .chart>details{margin-top:var(--space-3)}

/* Disclosure */
.stats-dash details>summary{cursor:pointer;font-size:var(--fs-sm);color:var(--accent);list-style-position:inside;width:fit-content;border-radius:4px}
.stats-dash details>summary:hover{color:var(--accent-hover)}

/* Tabs */
.stats-dash .tabs{display:flex;gap:var(--space-1);border-bottom:1px solid var(--border);margin-bottom:var(--space-4);overflow-x:auto;scrollbar-width:thin}
.stats-dash .tabs button{background:transparent;color:var(--text-2);border:0;border-bottom:2px solid transparent;border-radius:0;font-weight:500;font-size:var(--fs-md);padding:8px 12px;white-space:nowrap}
.stats-dash .tabs button:hover{background:transparent;color:var(--text)}
.stats-dash .tabs button[aria-selected=true]{color:var(--text);border-bottom-color:var(--accent);font-weight:650}
.stats-dash .tabpanel>.kpis{margin-bottom:var(--space-4)}
.stats-dash .tabpanel>.chart{margin-bottom:var(--space-4)}

/* Report cards */
.stats-dash .cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4)}
.stats-dash .cards>.card:has(details[open]){grid-column:1/-1}
.stats-dash .report{display:flex;flex-direction:column}
.stats-dash .card-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--space-2) var(--space-4);margin-top:var(--space-2)}
.stats-dash .card-foot details{flex-basis:100%}
.stats-dash .card-foot details[open]>summary{margin-bottom:var(--space-2)}
.stats-dash .table-scroll{overflow-x:auto;max-width:100%;-webkit-overflow-scrolling:touch}
.stats-dash table{width:100%;border-collapse:collapse;font-size:var(--fs-sm);font-variant-numeric:tabular-nums}
.stats-dash th,.stats-dash td{padding:6px 8px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.stats-dash th{font-size:var(--fs-xs);color:var(--muted);font-weight:600;white-space:nowrap}
.stats-dash td.num,.stats-dash th.num{text-align:right;white-space:nowrap}
.stats-dash td.key{overflow-wrap:anywhere;min-width:10ch}
.stats-dash tbody tr:hover{background:var(--surface-2)}
.stats-dash th button.sort{background:none;border:0;padding:0;min-height:0;color:inherit;font:inherit;font-weight:inherit;text-align:inherit;cursor:pointer}
.stats-dash th button.sort:hover{background:none;color:var(--text)}
.stats-dash th[aria-sort=ascending] button.sort::after{content:" ↑"}.stats-dash th[aria-sort=descending] button.sort::after{content:" ↓"}
.stats-dash table.compact{table-layout:fixed}
.stats-dash table.compact th:nth-child(2),.stats-dash table.compact td:nth-child(2){width:7.5em}
.stats-dash table.compact th{white-space:normal;vertical-align:bottom}
.stats-dash table.compact th:nth-child(3),.stats-dash table.compact td:nth-child(3){width:4.5em;color:var(--muted)}
.stats-dash table.compact th:nth-child(3){color:var(--muted)}
.stats-dash table.compact td,.stats-dash table.compact th{border-bottom:0;padding:3px 6px}
.stats-dash table.compact thead th{border-bottom:1px solid var(--border);padding-bottom:6px}
.stats-dash table.compact tbody tr:first-child td{padding-top:6px}
.stats-dash table.compact tbody tr:hover{background:transparent}
.stats-dash table.compact tbody tr:hover .bar-row{background:var(--surface-2)}
.stats-dash .bar-row{position:relative;padding:3px 8px;border-radius:5px;min-height:26px}
.stats-dash .bar-row .fill{position:absolute;left:0;top:0;bottom:0;background:var(--bar);border-radius:5px}
.stats-dash .bar-row .lbl,.stats-dash .bar-row .sub{position:relative;display:block}
.stats-dash .bar-row .sub{font-size:var(--fs-xs);color:var(--muted)}
.stats-dash table.compact td.num{padding-top:6px}
.stats-dash.js table.compact tr.more{display:none}
.stats-dash.js .report.expanded table.compact tr.more{display:table-row}

/* Donut */
.stats-dash .donut-wrap{display:flex;gap:var(--space-5);align-items:center;margin-bottom:var(--space-4);flex-wrap:wrap}
.stats-dash .donut{width:148px;height:148px;flex:none}
.stats-dash .donut circle{fill:none;stroke-width:7}
.stats-dash .donut .track{stroke:var(--surface-2)}
.stats-dash .s0{color:var(--s0);stroke:var(--s0)}.stats-dash .s1{color:var(--s1);stroke:var(--s1)}.stats-dash .s2{color:var(--s2);stroke:var(--s2)}
.stats-dash .s3{color:var(--s3);stroke:var(--s3)}.stats-dash .s4{color:var(--s4);stroke:var(--s4)}.stats-dash .s-other{color:var(--s-other);stroke:var(--s-other)}
.stats-dash .legend{display:grid;gap:4px;font-size:var(--fs-sm);margin:0;padding:0;list-style:none;flex:1 1 180px;min-width:0}
.stats-dash .legend li{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:var(--space-2);align-items:center;color:var(--muted);font-variant-numeric:tabular-nums}
.stats-dash .legend .swatch{width:10px;height:10px;border-radius:3px}
.stats-dash .legend .s0 .swatch{background:var(--s0)}.stats-dash .legend .s1 .swatch{background:var(--s1)}.stats-dash .legend .s2 .swatch{background:var(--s2)}
.stats-dash .legend .s3 .swatch{background:var(--s3)}.stats-dash .legend .s4 .swatch{background:var(--s4)}.stats-dash .legend .s-other .swatch{background:var(--s-other)}
.stats-dash .legend .name{color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Sites */
.stats-dash .page-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--space-3);margin-bottom:var(--space-4)}
.stats-dash .page-head h1{font-size:var(--fs-xl)}
.stats-dash .stack{display:grid;gap:var(--space-4)}
.stats-dash .site-card .meta{display:flex;flex-wrap:wrap;gap:var(--space-2);font-size:var(--fs-sm);color:var(--muted);margin-bottom:var(--space-4)}
.stats-dash .badge{display:inline-block;font-size:var(--fs-xs);padding:1px 8px;border-radius:999px;background:var(--surface-2);color:var(--text-2);border:1px solid var(--border);overflow-wrap:anywhere}
.stats-dash .badge.accent{background:var(--accent-soft);color:var(--accent);border-color:transparent}
.stats-dash .section{border-top:1px solid var(--border);padding-top:var(--space-4);margin-top:var(--space-4)}
.stats-dash .site-card h3{font-size:var(--fs-sm);margin-bottom:var(--space-2)}
.stats-dash .site-card .help{font-size:var(--fs-xs);color:var(--muted);margin:0 0 var(--space-2)}
.stats-dash .notice .lead{margin:0 0 var(--space-3)}
.stats-dash .copy-box{position:relative}
.stats-dash .copy-box pre{padding-right:84px}
.stats-dash .copy-box button{position:absolute;top:6px;right:6px;min-height:28px;padding:3px 10px;font-size:var(--fs-xs)}
.stats-dash .copy-status{font-size:var(--fs-xs);color:var(--muted);min-height:1em;margin:4px 0 0}
.stats-dash .site-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4)}
.stats-dash .site-form .field{display:flex;flex-direction:column;gap:4px;min-width:0}
.stats-dash .site-form .field.wide,.stats-dash .site-form .error,.stats-dash .site-form .actions{grid-column:1/-1}
.stats-dash .site-form label{font-size:var(--fs-sm);font-weight:600;color:var(--text)}
.stats-dash .site-form .help{font-size:var(--fs-xs);color:var(--muted);margin:0}
.stats-dash .site-form input:not([type=checkbox]),.stats-dash .site-form select{width:100%}
.stats-dash .site-form .check{display:flex;flex-direction:row;align-items:center;gap:8px}
.stats-dash .site-form .check input{min-height:0;width:16px;height:16px}
.stats-dash .site-form .error{border:1px solid var(--bad);border-radius:var(--radius-sm);padding:var(--space-2) var(--space-3);font-size:var(--fs-sm)}
.stats-dash .site-form .error ul{margin:0;padding-left:18px}
.stats-dash .site-card>details>summary{font-weight:600;margin-top:var(--space-2)}
.stats-dash .site-card>details[open]>summary{margin-bottom:var(--space-4)}
.stats-dash .key-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--space-3)}
.stats-dash .key-row p{margin:0;font-size:var(--fs-sm);color:var(--text-2);flex:1 1 280px}

.stats-dash footer{font-size:var(--fs-xs);color:var(--muted);margin:var(--space-6) 0 var(--space-4);max-width:80ch}

@media (max-width:900px){.stats-dash .cards{grid-template-columns:minmax(0,1fr)}}
@media (max-width:640px){
  .stats-dash .card,.stats-dash .panel{padding:var(--space-3) var(--space-4)}
  .stats-dash .kpis.primary{grid-template-columns:repeat(2,minmax(0,1fr))}
  .stats-dash .kpis.secondary{grid-template-columns:repeat(2,minmax(0,1fr))}
  .stats-dash .kpi .value{font-size:var(--fs-xl)}
  .stats-dash .kpis.secondary .value{font-size:var(--fs-md)}
  .stats-dash svg.spark{width:48px}
  .stats-dash .delta .vs{display:none}
  .stats-dash .x-axis .minor{display:none}
  .stats-dash .plot,.stats-dash .y-axis{height:180px}
  .stats-dash .toolbar,.stats-dash .toolbar form{width:100%}
  .stats-dash .toolbar label{flex:1 1 140px}
  .stats-dash .toolbar select,.stats-dash .toolbar input{width:100%}
  .stats-dash .site-form{grid-template-columns:minmax(0,1fr)}
  .stats-dash .donut{width:120px;height:120px}
}
@media (prefers-reduced-motion:no-preference){
  .stats-dash button,.stats-dash a,.stats-dash .metric-tabs label,.stats-dash .bar-row{transition:background-color .15s,color .15s,border-color .15s}
}
@media (prefers-reduced-motion:reduce){.stats-dash *{transition:none!important;animation:none!important;scroll-behavior:auto!important}}
@media (forced-colors:active){.stats-dash .bar-row .fill,.stats-dash .plot .area{forced-color-adjust:none;background:Highlight;opacity:.3}}
</style>
