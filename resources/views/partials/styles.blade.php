<style>
/* Design tokens, after Filament's default look (zinc grays, amber primary; Tailwind's palette): light theme by
   default, dark theme from prefers-color-scheme (inside a host's admin layout, .stats-embedded, only when the layout
   follows the system theme, .stats-theme-system: most admin layouts stay light); inside a
   Filament panel (.stats-filament) its own primary colour and .dark class. */
.stats-dash{
  color-scheme:light;
  --bg:#fafafa;--surface:#ffffff;--surface-2:#fafafa;--text:#09090b;--text-2:#3f3f46;--muted:#71717a;
  --border:rgba(9,9,11,.08);--border-strong:rgba(9,9,11,.14);--accent:#d97706;--accent-hover:#b45309;--on-accent:#ffffff;
  --accent-soft:rgba(245,158,11,.12);--bar:rgba(9,9,11,.05);--area:rgba(245,158,11,.12);
  --good:#16a34a;--bad:#dc2626;--flat:#71717a;--grid:#f4f4f5;
  --s0:#f59e0b;--s1:#3b82f6;--s2:#10b981;--s3:#8b5cf6;--s4:#f43f5e;--s5:#06b6d4;--s-other:#a1a1aa;
  --shadow:0 1px 2px rgba(9,9,11,.05);--card-shadow:0 1px 2px rgba(9,9,11,.06),0 6px 16px rgba(9,9,11,.08);
  --radius:12px;--radius-sm:8px;
  --space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:24px;--space-6:32px;
  --fs-xs:12px;--fs-sm:14px;--fs-md:14px;--fs-lg:16px;--fs-xl:20px;--fs-2xl:30px;
  --font:"Inter var",Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","PingFang SC","Microsoft YaHei",sans-serif;
  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  color:var(--text);font:var(--fs-md)/1.5 var(--font);-webkit-text-size-adjust:100%;
}
@media (prefers-color-scheme:dark){.stats-dash:not(.stats-filament):not(.stats-embedded),.stats-dash.stats-embedded.stats-theme-system{
  color-scheme:dark;
  --bg:#09090b;--surface:#18181b;--surface-2:rgba(255,255,255,.04);--text:#fafafa;--text-2:#d4d4d8;--muted:#a1a1aa;
  --border:rgba(255,255,255,.10);--border-strong:rgba(255,255,255,.20);--accent:#fbbf24;--accent-hover:#fcd34d;--on-accent:#09090b;
  --accent-soft:rgba(251,191,36,.14);--bar:rgba(255,255,255,.07);--area:rgba(251,191,36,.14);
  --good:#4ade80;--bad:#f87171;--flat:#a1a1aa;--grid:rgba(255,255,255,.06);
  --s0:#f59e0b;--s1:#3b82f6;--s2:#10b981;--s3:#8b5cf6;--s4:#f43f5e;--s5:#06b6d4;--s-other:#52525b;
  --shadow:none;--card-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 2px 4px rgba(0,0,0,.45),0 12px 28px rgba(0,0,0,.55);
}}
.stats-dash.stats-filament{
  --accent:var(--primary-600,#d97706);--accent-hover:var(--primary-500,#f59e0b);--s0:var(--primary-500,#f59e0b);
  --bar:color-mix(in oklab,var(--primary-500,#f59e0b) 14%,transparent);--area:color-mix(in oklab,var(--primary-500,#f59e0b) 12%,transparent);
}
.dark .stats-dash.stats-filament{
  color-scheme:dark;
  --bg:transparent;--surface:var(--gray-900,#18181b);--surface-2:rgba(255,255,255,.04);--text:#fafafa;--text-2:#d4d4d8;--muted:#a1a1aa;
  --border:rgba(255,255,255,.10);--border-strong:rgba(255,255,255,.20);--accent:var(--primary-400,#fbbf24);--accent-hover:var(--primary-300,#fcd34d);--on-accent:#09090b;
  --accent-soft:color-mix(in oklab,var(--primary-400,#fbbf24) 14%,transparent);--bar:color-mix(in oklab,var(--primary-400,#fbbf24) 16%,transparent);--area:color-mix(in oklab,var(--primary-400,#fbbf24) 14%,transparent);
  --good:#4ade80;--bad:#f87171;--flat:#a1a1aa;--grid:rgba(255,255,255,.06);
  --s1:#3b82f6;--s2:#10b981;--s3:#8b5cf6;--s4:#f43f5e;--s5:#06b6d4;--s-other:#52525b;
  --shadow:none;--card-shadow:none;
}
.stats-dash *,.stats-dash *::before,.stats-dash *::after{box-sizing:border-box}
.stats-dash [hidden]{display:none!important}
.stats-dash a{color:var(--accent);text-decoration-thickness:1px;text-underline-offset:2px}
.stats-dash a:hover{color:var(--accent-hover)}
.stats-dash a.stats-back{display:inline-flex;align-items:center;gap:4px;color:var(--text-2);text-decoration:none;font-size:var(--fs-sm);font-weight:500;padding:6px 10px 6px 6px;border-radius:var(--radius-sm)}
.stats-dash a.stats-back:hover{background:var(--surface-2);color:var(--text)}
.stats-dash a.stats-back svg{width:18px;height:18px;flex:none}
.stats-dash.stats-embedded nav.stats-main{display:flex;gap:var(--space-1);margin-left:auto}
.stats-dash.stats-embedded nav.stats-main a{color:var(--text-2);text-decoration:none;font-size:var(--fs-sm);font-weight:500;padding:6px 10px;border-radius:var(--radius-sm)}
.stats-dash.stats-embedded nav.stats-main a:hover{background:var(--surface-2);color:var(--text)}
.stats-dash.stats-embedded nav.stats-main a[aria-current=page]{color:var(--accent);background:var(--accent-soft)}
.stats-dash .stats-topbar{display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-5)}
.stats-dash h1,.stats-dash h2,.stats-dash h3{font-weight:600;line-height:1.4;margin:0}
.stats-dash :focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:4px}
.stats-dash .stats-muted{color:var(--muted)}
.stats-dash .stats-error{color:var(--bad)}
.stats-dash .stats-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.stats-dash code,.stats-dash pre{font-family:var(--mono);font-size:var(--fs-xs)}
.stats-dash pre{margin:0;background:var(--surface-2);border:1px solid var(--border);padding:var(--space-3);border-radius:var(--radius-sm);overflow-x:auto;white-space:pre-wrap;word-break:break-all}

/* Controls */
.stats-dash input,.stats-dash select,.stats-dash textarea,.stats-dash button{font:inherit;font-size:var(--fs-sm);color:var(--text);background:var(--surface);border:0;box-shadow:0 0 0 1px var(--border-strong),var(--shadow);border-radius:var(--radius-sm);padding:6px 12px;min-height:36px;max-width:100%}
.stats-dash select{padding-right:28px}
.stats-dash textarea{width:100%;min-height:84px;font-family:var(--mono);font-size:var(--fs-xs);resize:vertical}
.stats-dash input:focus,.stats-dash select:focus,.stats-dash textarea:focus{outline:0;box-shadow:0 0 0 2px var(--accent)}
.stats-dash [aria-invalid=true]{box-shadow:0 0 0 1px var(--bad)}
.stats-dash .stats-button{font-size:var(--fs-sm);border:0;border-radius:var(--radius-sm);padding:8px 12px;min-height:36px}
.stats-dash button,.stats-dash .stats-button{cursor:pointer;background:var(--accent);box-shadow:var(--shadow);color:var(--on-accent);font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.stats-dash button:hover,.stats-dash .stats-button:hover{background:var(--accent-hover);color:var(--on-accent)}
.stats-dash button.stats-secondary,.stats-dash .stats-button.stats-secondary{background:var(--surface);color:var(--text);box-shadow:0 0 0 1px var(--border-strong),var(--shadow)}
.stats-dash button.stats-secondary:hover,.stats-dash .stats-button.stats-secondary:hover{background:var(--surface-2);color:var(--text)}
.stats-dash button.stats-link{background:none;border:0;box-shadow:none;color:var(--accent);padding:4px 0;min-height:0;font-weight:500}
.stats-dash button.stats-link:hover{text-decoration:underline;background:none;color:var(--accent-hover)}

/* Cards */
.stats-dash .stats-card,.stats-dash .stats-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--card-shadow);padding:var(--space-5);min-width:0}
.stats-dash .stats-card-head{display:flex;align-items:baseline;justify-content:space-between;gap:var(--space-3);margin-bottom:var(--space-3)}
/* A card's title bar: a tinted strip across the top of the card, divided from its content */
.stats-dash .stats-card>.stats-card-head{margin:calc(-1 * var(--space-5)) calc(-1 * var(--space-5)) var(--space-5);padding:var(--space-4) var(--space-5);background:var(--surface-2);border-bottom:1px solid var(--border);border-radius:var(--radius) var(--radius) 0 0}
.stats-dash .stats-card-head h2{font-size:var(--fs-lg)}
.stats-dash .stats-card-head h3{font-size:var(--fs-lg)}
.stats-dash .stats-card-head a{font-size:var(--fs-xs);font-weight:500;white-space:nowrap;text-decoration:none;color:var(--text-2);padding:4px 10px;border-radius:var(--radius-sm);box-shadow:0 0 0 1px var(--border-strong),var(--shadow);background:var(--surface)}
.stats-dash .stats-card-head a:hover{color:var(--text);background:var(--surface-2)}
.stats-dash .stats-notice{border-left:4px solid var(--accent)}
.stats-dash .stats-empty{color:var(--muted);font-size:var(--fs-sm);margin:var(--space-5) 0;text-align:center}

/* Toolbar */
.stats-dash .stats-toolbar{display:flex;flex-wrap:wrap;gap:var(--space-3);align-items:flex-end;justify-content:space-between;margin-bottom:var(--space-2)}
.stats-dash .stats-toolbar form{display:flex;flex-wrap:wrap;gap:var(--space-2);align-items:flex-end;margin:0;min-width:0}
.stats-dash .stats-toolbar label{display:flex;flex-direction:column;gap:2px;font-size:var(--fs-xs);color:var(--muted);min-width:0}
.stats-dash .stats-site-switch select{font-size:24px;font-weight:700;box-shadow:none;border-color:transparent;background:transparent;padding:4px 32px 4px 6px;margin-left:-6px;max-width:100%}
.stats-dash .stats-site-switch select:hover{border-color:var(--border-strong)}
.stats-dash .stats-site-switch .stats-site-name{display:block;font-size:24px;font-weight:700;letter-spacing:-.01em;padding:4px 0}
.stats-dash .stats-range-form .stats-custom{display:flex;flex-wrap:wrap;gap:var(--space-2)}
.stats-dash .stats-period{font-size:var(--fs-sm);color:var(--muted);margin:0 0 var(--space-5)}
.stats-dash .stats-period strong{color:var(--text-2);font-weight:600}
.stats-dash .stats-period p{margin:0}
.stats-dash .stats-alert{color:var(--bad);border:1px solid var(--bad);border-radius:var(--radius-sm);padding:var(--space-2) var(--space-3);margin:var(--space-2) 0 0}

/* KPI strip */
.stats-dash .stats-kpis{display:grid;gap:var(--space-4);margin-bottom:var(--space-4)}
.stats-dash .stats-kpis.stats-primary{grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr))}
.stats-dash .stats-kpis.stats-secondary{grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));margin-bottom:var(--space-5)}
.stats-dash .stats-kpi{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--card-shadow);padding:var(--space-5);min-width:0;display:flex;flex-direction:column;gap:var(--space-2)}
.stats-dash .stats-kpi .stats-label{font-size:var(--fs-sm);font-weight:500;color:var(--muted)}
.stats-dash .stats-kpi .stats-value{font-size:var(--fs-2xl);font-weight:600;line-height:1.2;font-variant-numeric:tabular-nums;letter-spacing:-.02em}
.stats-dash .stats-kpis.stats-secondary .stats-kpi{padding:var(--space-4) var(--space-5);background:var(--surface)}
.stats-dash .stats-kpis.stats-secondary .stats-value{font-size:var(--fs-xl);font-weight:600}
/* Label, value and change from the top, the trend line across the bottom (like Filament's stats overview) */
.stats-dash .stats-kpi .stats-foot{display:flex;flex-direction:column;align-items:stretch;gap:var(--space-2);margin-top:auto;min-width:0}
.stats-dash .stats-delta{font-size:var(--fs-xs);font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap}
.stats-dash .stats-delta .stats-vs{font-weight:400;color:var(--muted)}
.stats-dash .stats-delta.stats-good{color:var(--good)}.stats-dash .stats-delta.stats-bad{color:var(--bad)}.stats-dash .stats-delta.stats-flat{color:var(--flat)}
.stats-dash .stats-note{font-size:var(--fs-xs);color:var(--muted)}
.stats-dash svg.stats-spark{display:block;width:100%;height:32px;overflow:visible}
.stats-dash svg.stats-spark path{fill:none;stroke:var(--accent);stroke-width:1.5;stroke-linejoin:round;stroke-linecap:round;opacity:.85}
.stats-dash .stats-kpi .stats-live{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--good);margin-right:6px;vertical-align:middle}

/* Line chart */
.stats-dash .stats-chart{margin-bottom:var(--space-5)}
.stats-dash .stats-chart .stats-card-head{flex-wrap:wrap;align-items:center}
.stats-dash .stats-metric-radio{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
.stats-dash .stats-metric-tabs{display:flex;flex-wrap:wrap;gap:2px;background:var(--surface-2);border-radius:var(--radius-sm);padding:3px}
.stats-dash .stats-metric-tabs label{font-size:var(--fs-sm);padding:4px 10px;border-radius:6px;color:var(--text-2);cursor:pointer;white-space:nowrap}
.stats-dash .stats-metric-tabs label:hover{color:var(--text)}
.stats-dash .stats-plot-wrap{display:grid;grid-template-columns:auto minmax(0,1fr);column-gap:var(--space-2);margin-top:var(--space-2)}
.stats-dash .stats-y-axis{position:relative;height:240px;font-size:11px;color:var(--muted);font-variant-numeric:tabular-nums;text-align:right;min-width:2.5em}
.stats-dash .stats-y-axis span{position:absolute;right:0;transform:translateY(-50%);white-space:nowrap}
.stats-dash .stats-plot{position:relative;height:240px;border-radius:4px;touch-action:pan-y}
.stats-dash .stats-plot svg{display:block;width:100%;height:100%;overflow:visible}
.stats-dash .stats-plot .stats-grid{stroke:var(--grid);stroke-width:1}
.stats-dash .stats-plot .stats-baseline{stroke:var(--border-strong);stroke-width:1}
.stats-dash .stats-plot .stats-area{fill:var(--area);stroke:none}
.stats-dash .stats-plot .stats-line{fill:none;stroke:var(--accent);stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
.stats-dash .stats-x-axis{grid-column:2;position:relative;height:22px;font-size:11px;color:var(--muted);margin-top:4px}
.stats-dash .stats-x-axis span{position:absolute;top:0;white-space:nowrap}
.stats-dash .stats-x-axis .stats-middle{transform:translateX(-50%)}.stats-dash .stats-x-axis .stats-end{transform:translateX(-100%)}
.stats-dash .stats-layer{display:none}
.stats-dash .stats-m0:checked~.stats-plot-wrap .stats-l0,.stats-dash .stats-m1:checked~.stats-plot-wrap .stats-l1,.stats-dash .stats-m2:checked~.stats-plot-wrap .stats-l2,
.stats-dash .stats-m3:checked~.stats-plot-wrap .stats-l3,.stats-dash .stats-m4:checked~.stats-plot-wrap .stats-l4,.stats-dash .stats-m5:checked~.stats-plot-wrap .stats-l5{display:inline}
.stats-dash .stats-m0:checked~.stats-card-head .stats-t0,.stats-dash .stats-m1:checked~.stats-card-head .stats-t1,.stats-dash .stats-m2:checked~.stats-card-head .stats-t2,
.stats-dash .stats-m3:checked~.stats-card-head .stats-t3,.stats-dash .stats-m4:checked~.stats-card-head .stats-t4,.stats-dash .stats-m5:checked~.stats-card-head .stats-t5{background:var(--surface);color:var(--text);font-weight:600;box-shadow:var(--shadow);outline:1px solid var(--border)}
.stats-dash .stats-m0:focus-visible~.stats-card-head .stats-t0,.stats-dash .stats-m1:focus-visible~.stats-card-head .stats-t1,.stats-dash .stats-m2:focus-visible~.stats-card-head .stats-t2,
.stats-dash .stats-m3:focus-visible~.stats-card-head .stats-t3,.stats-dash .stats-m4:focus-visible~.stats-card-head .stats-t4,.stats-dash .stats-m5:focus-visible~.stats-card-head .stats-t5{outline:2px solid var(--accent);outline-offset:1px}
.stats-dash .stats-guide{position:absolute;top:0;bottom:0;width:0;border-left:1px dashed var(--border-strong);pointer-events:none}
.stats-dash .stats-dot{position:absolute;width:10px;height:10px;margin:-5px 0 0 -5px;border-radius:50%;background:var(--accent);border:2px solid var(--surface);pointer-events:none}
.stats-dash .stats-tip{position:absolute;top:8px;z-index:2;min-width:150px;max-width:240px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:var(--radius-sm);box-shadow:0 4px 16px rgba(0,0,0,.14);padding:var(--space-2) var(--space-3);font-size:var(--fs-xs);pointer-events:none}
.stats-dash .stats-tip .stats-day{font-weight:600;margin-bottom:4px;color:var(--text-2)}
.stats-dash .stats-tip dl{display:grid;grid-template-columns:1fr auto;gap:2px var(--space-3);margin:0}
.stats-dash .stats-tip dt{color:var(--muted)}.stats-dash .stats-tip dd{margin:0;text-align:right;font-variant-numeric:tabular-nums}
.stats-dash .stats-tip .stats-on{color:var(--text);font-weight:650}
.stats-dash .stats-chart>details{margin-top:var(--space-3)}

/* Disclosure */
.stats-dash details>summary{cursor:pointer;font-size:var(--fs-sm);color:var(--accent);list-style-position:inside;width:fit-content;border-radius:4px}
.stats-dash details>summary:hover{color:var(--accent-hover)}

/* Tabs */
.stats-dash .stats-tabs{display:flex;gap:var(--space-1);padding:var(--space-2);background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--card-shadow);margin-bottom:var(--space-4);overflow-x:auto;scrollbar-width:thin}
.stats-dash .stats-tabs button{background:transparent;color:var(--text-2);border:0;box-shadow:none;border-radius:var(--radius-sm);font-weight:600;font-size:15px;padding:8px 14px;white-space:nowrap}
.stats-dash .stats-tabs button:hover{background:var(--surface-2);color:var(--text)}
.stats-dash .stats-tabs button[aria-selected=true]{color:#09090b;background:var(--s0)}
.stats-dash .stats-tabpanel>.stats-kpis{margin-bottom:var(--space-4)}
.stats-dash .stats-tabpanel>.stats-chart{margin-bottom:var(--space-4)}

/* Report cards: two columns that each stack their cards (partials.dashboard deals them out alternately) */
.stats-dash .stats-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4);align-items:start}
.stats-dash .stats-column{display:grid;gap:var(--space-4);min-width:0}
.stats-dash .stats-cell{min-width:0}
.stats-dash .stats-cell>.stats-card{height:auto}
.stats-dash .stats-tabpanel>.stats-chart-card.stats-wide{margin-bottom:var(--space-4)}
.stats-dash .stats-report{display:flex;flex-direction:column}
.stats-dash .stats-card-foot{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--space-2) var(--space-4);margin-top:var(--space-2)}
.stats-dash .stats-card-foot details{flex-basis:100%}
.stats-dash .stats-card-foot details[open]>summary{margin-bottom:var(--space-2)}
.stats-dash .stats-table-scroll{overflow-x:auto;max-width:100%;-webkit-overflow-scrolling:touch}
.stats-dash table{width:100%;border-collapse:collapse;font-size:var(--fs-sm);font-variant-numeric:tabular-nums}
.stats-dash th,.stats-dash td{padding:12px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.stats-dash th{font-size:var(--fs-sm);color:var(--text);font-weight:600;white-space:nowrap;background:var(--surface-2)}
.stats-dash td.stats-num,.stats-dash th.stats-num{text-align:right;white-space:nowrap}
.stats-dash td.stats-key{overflow-wrap:anywhere;min-width:10ch}
.stats-dash tbody tr:hover{background:var(--surface-2)}
.stats-dash th button.stats-sort{background:none;border:0;padding:0;min-height:0;color:inherit;font:inherit;font-weight:inherit;text-align:inherit;cursor:pointer}
.stats-dash th button.stats-sort:hover{background:none;color:var(--text)}
.stats-dash th[aria-sort=ascending] button.stats-sort::after{content:" ↑"}.stats-dash th[aria-sort=descending] button.stats-sort::after{content:" ↓"}
.stats-dash table.stats-compact{table-layout:fixed}
.stats-dash table.stats-compact th:nth-child(2),.stats-dash table.stats-compact td:nth-child(2){width:7.5em}
.stats-dash table.stats-compact th{white-space:normal;vertical-align:bottom}
.stats-dash table.stats-compact th:nth-child(3),.stats-dash table.stats-compact td:nth-child(3){width:4.5em;color:var(--muted)}
.stats-dash table.stats-compact th:nth-child(3){color:var(--muted)}
.stats-dash table.stats-compact td,.stats-dash table.stats-compact th{border-bottom:0;padding:3px 6px}
.stats-dash table.stats-compact thead th{border-bottom:1px solid var(--border);padding:8px 6px}
.stats-dash table.stats-compact tbody tr:first-child td{padding-top:6px}
.stats-dash table.stats-compact tbody tr:hover{background:transparent}
.stats-dash table.stats-compact tbody tr:hover .stats-bar-row{background:var(--surface-2)}
.stats-dash .stats-bar-row{position:relative;padding:3px 8px;border-radius:5px;min-height:26px}
.stats-dash .stats-bar-row .stats-fill{position:absolute;left:0;top:0;bottom:0;background:var(--bar);border-radius:5px}
.stats-dash .stats-bar-row .stats-lbl,.stats-dash .stats-bar-row .stats-sub{position:relative;display:block}
.stats-dash .stats-bar-row .stats-sub{font-size:var(--fs-xs);color:var(--muted)}
.stats-dash table.stats-compact td.stats-num{padding-top:6px}
.stats-dash.stats-js table.stats-compact tr.stats-more{display:none}
.stats-dash.stats-js .stats-report.stats-expanded table.stats-compact tr.stats-more{display:table-row}

/* Donut */
.stats-dash .stats-donut-wrap{display:flex;gap:var(--space-5);align-items:center;margin-bottom:var(--space-4);flex-wrap:wrap}
.stats-dash .stats-donut{width:148px;height:148px;flex:none}
.stats-dash .stats-donut circle{fill:none;stroke-width:7}
.stats-dash .stats-donut .stats-track{stroke:var(--surface-2)}
.stats-dash .stats-s0{color:var(--s0);stroke:var(--s0)}.stats-dash .stats-s1{color:var(--s1);stroke:var(--s1)}.stats-dash .stats-s2{color:var(--s2);stroke:var(--s2)}
.stats-dash .stats-s3{color:var(--s3);stroke:var(--s3)}.stats-dash .stats-s4{color:var(--s4);stroke:var(--s4)}.stats-dash .stats-s5{color:var(--s5);stroke:var(--s5)}.stats-dash .stats-s-other{color:var(--s-other);stroke:var(--s-other)}
.stats-dash .stats-legend{display:grid;gap:4px;font-size:var(--fs-sm);margin:0;padding:0;list-style:none;flex:1 1 180px;min-width:0}
.stats-dash .stats-legend li{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:var(--space-2);align-items:center;color:var(--muted);font-variant-numeric:tabular-nums}
.stats-dash .stats-legend .stats-swatch{width:10px;height:10px;border-radius:3px}
.stats-dash .stats-legend .stats-s0 .stats-swatch{background:var(--s0)}.stats-dash .stats-legend .stats-s1 .stats-swatch{background:var(--s1)}.stats-dash .stats-legend .stats-s2 .stats-swatch{background:var(--s2)}
.stats-dash .stats-legend .stats-s3 .stats-swatch{background:var(--s3)}.stats-dash .stats-legend .stats-s4 .stats-swatch{background:var(--s4)}.stats-dash .stats-legend .stats-s5 .stats-swatch{background:var(--s5)}.stats-dash .stats-legend .stats-s-other .stats-swatch{background:var(--s-other)}
.stats-dash .stats-legend .stats-name{color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Filament statistics page: its widgets like the cards above, with a tinted title bar and raised from the page */
.fi-wi-widget .fi-section-header,.fi-wi-widget .fi-ta-header{background:color-mix(in oklab,currentColor 6%,transparent);border-bottom:1px solid color-mix(in oklab,currentColor 12%,transparent);border-radius:var(--radius-xl,12px) var(--radius-xl,12px) 0 0}
.fi-wi-widget .fi-section,.fi-wi-widget .fi-ta-ctn,.fi-wi-stats-overview-stat{box-shadow:0 1px 2px rgba(15,18,25,.08),0 6px 18px rgba(15,18,25,.10)}
.dark .fi-wi-widget .fi-section,.dark .fi-wi-widget .fi-ta-ctn,.dark .fi-wi-stats-overview-stat{box-shadow:inset 0 1px 0 rgba(255,255,255,.08),0 2px 4px rgba(0,0,0,.45),0 12px 28px rgba(0,0,0,.55)}

/* Filament statistics page: the report tabs, larger and with the selected one in the panel's primary colour */
.stats-tabs .fi-tabs-item{font-size:15px;font-weight:600}
.stats-tabs .fi-tabs-item.fi-active{background:var(--primary-500,#f59e0b)}
.stats-tabs .fi-tabs-item.fi-active .fi-tabs-item-label{color:var(--gray-950,#09090b)}

/* Filament statistics page: its two widget columns (Pages\StatsDashboard) fold into one in the original order */
@media (max-width:1023.98px){
  .stats-columns>.fi-grid>.fi-grid-col,.stats-columns>.fi-grid>.fi-grid-col>.fi-sc-component,.stats-column,
  .stats-column>.fi-grid,.stats-column>.fi-grid>.fi-grid-col,.stats-column>.fi-grid>.fi-grid-col>.fi-sc-component{display:contents}
}

/* Tab charts (partials/charts) */
.stats-dash .stats-chart-desc{font-size:var(--fs-xs);color:var(--muted);margin:0 0 var(--space-3)}
.stats-dash .stats-chart-card .stats-empty{margin:var(--space-4) 0}
.stats-dash .stats-hbars{list-style:none;margin:0;padding:0;display:grid;gap:var(--space-2)}
.stats-dash .stats-hbars li{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,3fr) auto;gap:var(--space-3);align-items:center;font-size:var(--fs-sm)}
.stats-dash .stats-hbar-label{min-width:0}
.stats-dash .stats-hbar-label .stats-lbl{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stats-dash .stats-hbar-label .stats-sub{display:block;font-size:var(--fs-xs);color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stats-dash .stats-hbar-track{height:10px;background:var(--surface-2);border-radius:5px;overflow:hidden}
.stats-dash .stats-hbar-fill{display:block;height:100%;background:var(--accent);border-radius:5px}
.stats-dash .stats-hbar-value{font-variant-numeric:tabular-nums;text-align:right;min-width:4.5em;color:var(--text-2)}
.stats-dash .stats-layer.stats-static{display:block}
.stats-dash .stats-legend.stats-inline{display:flex;flex-wrap:wrap;gap:var(--space-1) var(--space-4);margin-bottom:var(--space-2)}
.stats-dash .stats-legend.stats-inline li{display:flex;gap:6px}
.stats-dash .stats-series .stats-band{fill:currentColor;fill-opacity:.55;stroke:none}
.stats-dash .stats-series .stats-stroke{fill:none;stroke:currentColor;stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
.stats-dash .stats-plot .stats-prev{fill:none;stroke:var(--muted);stroke-width:1.5;stroke-dasharray:5 4;opacity:.8}
.stats-dash .stats-prev-note{font-size:var(--fs-xs);color:var(--muted);display:flex;align-items:center;gap:6px}
.stats-dash .stats-prev-note::before{content:"";width:18px;border-top:2px dashed var(--muted)}
.stats-dash .stats-world svg{display:block;width:100%;max-width:1000px;height:auto;margin:0 auto}
.stats-dash .stats-world path,.stats-dash .stats-world-legend .stats-swatch{fill:var(--surface-2);stroke:var(--surface);stroke-width:.5}
.stats-dash .stats-world circle{stroke:var(--surface);stroke-width:1}
.stats-dash .stats-world .stats-c1,.stats-dash .stats-world-legend .stats-swatch.stats-c1{fill:var(--accent);fill-opacity:.22;background:color-mix(in srgb,var(--accent) 22%,transparent)}
.stats-dash .stats-world .stats-c2,.stats-dash .stats-world-legend .stats-swatch.stats-c2{fill:var(--accent);fill-opacity:.38;background:color-mix(in srgb,var(--accent) 38%,transparent)}
.stats-dash .stats-world .stats-c3,.stats-dash .stats-world-legend .stats-swatch.stats-c3{fill:var(--accent);fill-opacity:.55;background:color-mix(in srgb,var(--accent) 55%,transparent)}
.stats-dash .stats-world .stats-c4,.stats-dash .stats-world-legend .stats-swatch.stats-c4{fill:var(--accent);fill-opacity:.75;background:color-mix(in srgb,var(--accent) 75%,transparent)}
.stats-dash .stats-world .stats-c5,.stats-dash .stats-world-legend .stats-swatch.stats-c5{fill:var(--accent);fill-opacity:.95;background:color-mix(in srgb,var(--accent) 95%,transparent)}
.stats-dash .stats-world path:hover,.stats-dash .stats-world circle:hover{stroke:var(--text);stroke-width:1}
.stats-dash .stats-world-legend{display:flex;align-items:center;justify-content:center;gap:4px;font-size:var(--fs-xs);color:var(--muted);margin-top:var(--space-2)}
.stats-dash .stats-world-legend .stats-swatch{display:inline-block;width:22px;height:10px;border-radius:2px;background:var(--surface-2)}
.stats-dash .stats-world-legend span:first-child{margin-right:4px}.stats-dash .stats-world-legend span:last-child{margin-left:4px}
.stats-dash .stats-flow svg{display:block;width:100%;max-width:800px;height:auto;margin:0 auto;overflow:visible}
.stats-dash .stats-flow .stats-link{fill:currentColor;fill-opacity:.32;stroke:none}
.stats-dash .stats-flow .stats-link:hover{fill-opacity:.6}
.stats-dash .stats-flow .stats-node{fill:var(--text-2)}
.stats-dash .stats-flow text{font-size:12px;fill:var(--text)}
.stats-dash .stats-flow text .stats-count{fill:var(--muted)}
.stats-dash .stats-chart-card>details,.stats-dash .stats-chart-card details{margin-top:var(--space-3)}

/* Sites */
.stats-dash .stats-page-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--space-3);margin-bottom:var(--space-4)}
.stats-dash .stats-page-head h1{font-size:24px;font-weight:700;letter-spacing:-.01em}
.stats-dash .stats-stack{display:grid;gap:var(--space-4)}
.stats-dash .stats-site-card .stats-meta{display:flex;flex-wrap:wrap;gap:var(--space-2);font-size:var(--fs-sm);color:var(--muted);margin-bottom:var(--space-4)}
.stats-dash .stats-badge{display:inline-block;font-size:var(--fs-xs);padding:1px 8px;border-radius:999px;background:var(--surface-2);color:var(--text-2);border:1px solid var(--border);overflow-wrap:anywhere}
.stats-dash .stats-badge.stats-accent{background:var(--accent-soft);color:var(--accent);border-color:transparent}
.stats-dash .stats-section{border-top:1px solid var(--border);padding-top:var(--space-4);margin-top:var(--space-4)}
.stats-dash .stats-site-card h3{font-size:var(--fs-sm);margin-bottom:var(--space-2)}
.stats-dash .stats-site-card .stats-help{font-size:var(--fs-xs);color:var(--muted);margin:0 0 var(--space-2)}
.stats-dash .stats-notice .stats-lead{margin:0 0 var(--space-3)}
.stats-dash .stats-copy-box{position:relative}
.stats-dash .stats-copy-box pre{padding-right:84px}
.stats-dash .stats-copy-box button{position:absolute;top:6px;right:6px;min-height:28px;padding:3px 10px;font-size:var(--fs-xs)}
.stats-dash .stats-copy-status{font-size:var(--fs-xs);color:var(--muted);min-height:1em;margin:4px 0 0}
.stats-dash .stats-site-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4)}
.stats-dash .stats-site-form .stats-field{display:flex;flex-direction:column;gap:4px;min-width:0}
.stats-dash .stats-site-form .stats-field.stats-wide,.stats-dash .stats-site-form .stats-error,.stats-dash .stats-site-form .stats-actions{grid-column:1/-1}
.stats-dash .stats-site-form label{font-size:var(--fs-sm);font-weight:600;color:var(--text)}
.stats-dash .stats-site-form .stats-help{font-size:var(--fs-xs);color:var(--muted);margin:0}
.stats-dash .stats-site-form input:not([type=checkbox]),.stats-dash .stats-site-form select{width:100%}
.stats-dash .stats-site-form .stats-check{display:flex;flex-direction:row;align-items:center;gap:8px}
.stats-dash .stats-site-form .stats-check input{min-height:0;width:16px;height:16px}
.stats-dash .stats-site-form .stats-error{border:1px solid var(--bad);border-radius:var(--radius-sm);padding:var(--space-2) var(--space-3);font-size:var(--fs-sm)}
.stats-dash .stats-site-form .stats-error ul{margin:0;padding-left:18px}
.stats-dash .stats-site-card>details>summary{font-weight:600;margin-top:var(--space-2)}
.stats-dash details.stats-new-site>summary.stats-card-head{width:auto;color:var(--text);font-size:inherit;list-style:none;justify-content:flex-start;align-items:center;gap:var(--space-2)}
.stats-dash details.stats-new-site>summary.stats-card-head::-webkit-details-marker{display:none}
.stats-dash details.stats-new-site>summary.stats-card-head::before{content:"";width:7px;height:7px;border:solid var(--text-2);border-width:0 2px 2px 0;transform:rotate(-45deg);transition:transform .15s}
.stats-dash details.stats-new-site[open]>summary.stats-card-head::before{transform:rotate(45deg)}
.stats-dash details.stats-new-site:not([open]){padding-bottom:0}
.stats-dash details.stats-new-site:not([open])>summary.stats-card-head{margin-bottom:0;border-bottom:0;border-radius:var(--radius)}
.stats-dash .stats-site-card>details[open]>summary{margin-bottom:var(--space-4)}
.stats-dash .stats-key-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--space-3)}
.stats-dash .stats-key-row p{margin:0;font-size:var(--fs-sm);color:var(--text-2);flex:1 1 280px}

.stats-dash footer{font-size:var(--fs-xs);color:var(--muted);margin:var(--space-6) 0 var(--space-4);max-width:80ch}

@media (max-width:900px){.stats-dash .stats-columns{grid-template-columns:minmax(0,1fr)}.stats-dash .stats-column{display:contents}}
@media (max-width:640px){
  .stats-dash .stats-hbars li{grid-template-columns:minmax(0,1fr) auto}
  .stats-dash .stats-hbar-track{grid-column:1/-1;grid-row:2}
  .stats-dash .stats-card,.stats-dash .stats-panel{padding:var(--space-3) var(--space-4)}
  .stats-dash .stats-card>.stats-card-head{margin:calc(-1 * var(--space-3)) calc(-1 * var(--space-4)) var(--space-3);padding:var(--space-2) var(--space-4)}
  .stats-dash .stats-kpis.stats-primary{grid-template-columns:repeat(2,minmax(0,1fr))}
  .stats-dash .stats-kpis.stats-secondary{grid-template-columns:repeat(2,minmax(0,1fr))}
  .stats-dash .stats-kpi .stats-value{font-size:var(--fs-xl)}
  .stats-dash .stats-kpis.stats-secondary .stats-value{font-size:var(--fs-md)}
  .stats-dash .stats-delta .stats-vs{display:none}
  .stats-dash .stats-x-axis .stats-minor{display:none}
  .stats-dash .stats-plot,.stats-dash .stats-y-axis{height:180px}
  .stats-dash .stats-toolbar,.stats-dash .stats-toolbar form{width:100%}
  .stats-dash .stats-toolbar label{flex:1 1 140px}
  .stats-dash .stats-toolbar select,.stats-dash .stats-toolbar input{width:100%}
  .stats-dash .stats-site-form{grid-template-columns:minmax(0,1fr)}
  .stats-dash .stats-donut{width:120px;height:120px}
}
@media (prefers-reduced-motion:no-preference){
  .stats-dash button,.stats-dash a,.stats-dash .stats-metric-tabs label,.stats-dash .stats-bar-row{transition:background-color .15s,color .15s,border-color .15s}
}
@media (prefers-reduced-motion:reduce){.stats-dash *{transition:none!important;animation:none!important;scroll-behavior:auto!important}}
@media (forced-colors:active){.stats-dash .stats-bar-row .stats-fill,.stats-dash .stats-plot .stats-area{forced-color-adjust:none;background:Highlight;opacity:.3}}
</style>
