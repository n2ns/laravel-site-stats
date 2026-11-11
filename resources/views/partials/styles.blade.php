<style>
.stats-dash{--bg:#f6f7f9;--panel:#fff;--text:#1b1f24;--muted:#5d6672;--border:#dde1e6;--accent:#2563eb;--good:#1a7f37;--bad:#c62828;--hover:#f0f3f7;--bar:rgba(37,99,235,.18);--s0:#2563eb;--s1:#d9480f;--s2:#0f766e;--s3:#9333ea;--s4:#b45309;--s5:#64748b;color:var(--text);font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
@media (prefers-color-scheme:dark){.stats-dash:not(.stats-filament){--bg:#0f1216;--panel:#171b21;--text:#e6e9ee;--muted:#9aa4b1;--border:#2a3038;--accent:#6ea0ff;--good:#4cc472;--bad:#ff6b6b;--hover:#1e242c;--bar:rgba(110,160,255,.25);--s0:#6ea0ff;--s1:#ff8a5b;--s2:#2dd4bf;--s3:#c084fc;--s4:#fbbf24;--s5:#94a3b8}}
.dark .stats-dash.stats-filament{--bg:#0f1216;--panel:#171b21;--text:#e6e9ee;--muted:#9aa4b1;--border:#2a3038;--accent:#6ea0ff;--good:#4cc472;--bad:#ff6b6b;--hover:#1e242c;--bar:rgba(110,160,255,.25);--s0:#6ea0ff;--s1:#ff8a5b;--s2:#2dd4bf;--s3:#c084fc;--s4:#fbbf24;--s5:#94a3b8}
.stats-dash *{box-sizing:border-box}
.stats-dash [hidden]{display:none!important}
.stats-dash a{color:var(--accent)}
.stats-dash h1,.stats-dash h2,.stats-dash h3{font-weight:650}
.stats-dash .panel{background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px}
.stats-dash .toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:end}
.stats-dash .toolbar label{display:flex;flex-direction:column;font-size:12px;color:var(--muted);gap:2px}
.stats-dash input,.stats-dash select,.stats-dash textarea,.stats-dash button{font:inherit;color:var(--text);background:var(--panel);border:1px solid var(--border);border-radius:6px;padding:6px 8px}
.stats-dash textarea{width:100%;min-height:80px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.stats-dash button,.stats-dash .button{cursor:pointer;background:var(--accent);border-color:var(--accent);color:#fff;text-decoration:none;display:inline-block;padding:6px 12px;border-radius:6px}
.stats-dash button.secondary{background:transparent;color:var(--accent)}
.stats-dash :focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.stats-dash .muted{color:var(--muted)}
.stats-dash .error{color:var(--bad)}
.stats-dash .notice{border-left:4px solid var(--accent)}
.stats-dash .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.stats-dash .kpi{background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:12px}
.stats-dash .kpi .label{font-size:12px;color:var(--muted)}
.stats-dash .kpi .value{font-size:24px;font-weight:650;font-variant-numeric:tabular-nums}
.stats-dash .kpi .delta{font-size:12px}
.stats-dash .up{color:var(--good)}.stats-dash .down{color:var(--bad)}
.stats-dash .chart svg{width:100%;height:auto;display:block}
.stats-dash .chart .grid{stroke:var(--border)}
.stats-dash .chart .axis{fill:var(--muted);font-size:11px}
.stats-dash .chart path{fill:none;stroke-width:2}
.stats-dash .s0{color:var(--s0);stroke:var(--s0)}.stats-dash .s1{color:var(--s1);stroke:var(--s1)}.stats-dash .s2{color:var(--s2);stroke:var(--s2)}
.stats-dash .s3{color:var(--s3);stroke:var(--s3)}.stats-dash .s4{color:var(--s4);stroke:var(--s4)}.stats-dash .s5{color:var(--s5);stroke:var(--s5)}
.stats-dash circle.s0,.stats-dash circle.s1,.stats-dash circle.s2,.stats-dash circle.s3,.stats-dash circle.s4,.stats-dash circle.s5{fill:currentColor}
.stats-dash .donut circle{fill:none!important;stroke-width:6}
.stats-dash .legend{display:flex;flex-wrap:wrap;gap:4px 16px;font-size:12px;color:var(--muted);margin:0 0 6px;padding:0;list-style:none}
.stats-dash .legend .swatch{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:6px;vertical-align:middle;background:currentColor}
.stats-dash .legend .name{color:var(--text)}
.stats-dash .donut-wrap{display:flex;gap:16px;align-items:center;margin-bottom:8px}
.stats-dash .donut{width:110px;height:110px;flex:none}
.stats-dash .donut-wrap .legend{flex-direction:column}
.stats-dash .tabs{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid var(--border);margin-bottom:12px}
.stats-dash .tabs button{background:transparent;color:var(--muted);border:0;border-bottom:2px solid transparent;border-radius:0}
.stats-dash .tabs button[aria-selected=true]{color:var(--text);border-bottom-color:var(--accent)}
.stats-dash .reports{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:16px}
.stats-dash .report.wide{grid-column:1/-1}
.stats-dash .report h3{margin:0 0 8px;font-size:14px;display:flex;justify-content:space-between;gap:8px}
.stats-dash .report h3 a{font-weight:400;font-size:12px}
.stats-dash .table-scroll{overflow-x:auto}
.stats-dash table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums}
.stats-dash th,.stats-dash td{padding:5px 6px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.stats-dash th{font-size:12px;color:var(--muted);font-weight:600}
.stats-dash th button.sort{background:none;border:0;padding:0;color:inherit;font-weight:inherit;text-align:inherit}
.stats-dash th[aria-sort=ascending] button.sort::after{content:" ▲"}.stats-dash th[aria-sort=descending] button.sort::after{content:" ▼"}
.stats-dash td.num,.stats-dash th.num{text-align:right;white-space:nowrap}
.stats-dash td.key{overflow-wrap:anywhere;min-width:10ch}
.stats-dash td.share{min-width:90px}
.stats-dash .bar{display:block;height:4px;margin-top:2px;background:var(--bar);border-radius:2px}
.stats-dash .bar span{display:block;height:4px;background:var(--accent);border-radius:2px}
.stats-dash tbody tr:hover{background:var(--hover)}
.stats-dash code,.stats-dash pre{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
.stats-dash pre{background:var(--hover);padding:8px;border-radius:6px;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
.stats-dash .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
.stats-dash footer{font-size:12px;color:var(--muted);margin:24px 0}
.stats-dash .site-form{display:grid;gap:8px;max-width:640px}
.stats-dash .site-form label{display:flex;flex-direction:column;gap:2px;font-size:12px;color:var(--muted)}
.stats-dash .site-form label.check{flex-direction:row;align-items:center;gap:6px;color:var(--text)}
.stats-dash details>summary{cursor:pointer;font-weight:600}
@media (max-width:600px){.stats-dash .reports{grid-template-columns:1fr}.stats-dash .kpi .value{font-size:20px}}
</style>
