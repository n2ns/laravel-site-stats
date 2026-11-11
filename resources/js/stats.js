/*! N2N Stats | Apache-2.0 | cookieless: no cookies, no browser storage */
(function (w, d) {
  'use strict';
  var script = d.currentScript || d.querySelector('script[data-site]');
  if (!script) return;
  var attr = function (name) { return script.getAttribute(name); };
  var site = attr('data-site');
  var loc = w.location;
  var nav = w.navigator;
  var endpoint = attr('data-api') || script.src.replace(/\/js\/stats\.js(\?.*)?$/, '') + '/api/event';
  var list = function (value) {
    return value ? value.split(',').map(function (x) { return x.trim(); }).filter(Boolean) : [];
  };
  var exts = list(attr('data-download-extensions') ||
    'pdf,zip,gz,tgz,7z,rar,dmg,pkg,exe,msi,apk,aab,ipa,deb,rpm,appimage,vsix,xlsx,xls,docx,doc,pptx,csv,txt,mp3,mp4,mov,iso')
    .map(function (x) { return x.replace(/^\./, '').toLowerCase(); });
  var excluded = list(attr('data-exclude-paths')).map(function (p) {
    return new RegExp('^' + p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$');
  });
  var blocked = nav.webdriver || loc.protocol === 'file:' ||
    /^(localhost|127\.0\.0\.1|\[::1\])$/.test(loc.hostname);

  // Only the path and the parameters used for attribution leave the page: never the fragment or
  // other query parameters (tokens, e-mail addresses, …).
  var keep = /^(utm_[a-z]+|fbclid|gclid|gbraid|wbraid|dclid|msclkid|ttclid|li_fat_id|twclid)$/;
  function pageUrl() {
    var q = loc.search.replace(/^\?/, '').split('&').filter(function (p) {
      return p && keep.test(decodeURIComponent(p.split('=')[0]).toLowerCase());
    }).join('&');
    return loc.protocol + '//' + loc.host + loc.pathname + (q ? '?' + q : '');
  }
  function linkUrl(a) {
    return a.protocol + '//' + a.host + a.pathname;
  }
  // Resolves a URL against the page; scheme://host/path for http(s), otherwise undefined.
  function targetUrl(href) {
    if (href == null) return undefined;
    var a = d.createElement('a');
    a.href = String(href);
    return /^https?:$/.test(a.protocol) ? linkUrl(a) : undefined;
  }
  // Text the collector accepts: no control characters, trimmed, at most max characters; undefined when empty.
  function text(value, max) {
    if (value == null) return undefined;
    value = String(value).replace(/[\x00-\x1f\x7f]+/g, ' ').trim().slice(0, max).replace(/[\ud800-\udbff]$/, '');
    return value || undefined;
  }
  function meta(name) {
    var m = d.querySelector('meta[name="n2n-stats:' + name + '"]');
    return m && (m.getAttribute('content') || '');
  }
  function labelOf(el) {
    var tagged = el.closest('[data-stats-label]');
    return tagged ? text(tagged.getAttribute('data-stats-label'), 120) : undefined;
  }

  // path: the page the data belongs to (default the current one); skip: whether that page has the skip tag
  // (default: read now).
  function send(type, extra, path, skip) {
    try {
      if (blocked || !site || (skip === undefined ? meta('skip') != null : skip)) return;
      path = path || loc.pathname;
      for (var i = 0; i < excluded.length; i++) if (excluded[i].test(path)) return;
      var data = { s: site, t: type, u: pageUrl(), w: w.screen ? w.screen.width : undefined };
      for (var k in extra) data[k] = extra[k];
      var body = JSON.stringify(data);
      if (nav.sendBeacon && nav.sendBeacon(endpoint, new Blob([body], { type: 'text/plain' }))) return;
      if (w.fetch) {
        w.fetch(endpoint, {
          method: 'POST', body: body, keepalive: true, credentials: 'omit',
          headers: { 'Content-Type': 'text/plain' }
        }).catch(function () {});
      }
    } catch (e) { /* never break the page */ }
  }

  // data-pageviews="off": page views are counted on the server; the script only sends events.
  var pageviews = attr('data-pageviews') !== 'off';
  // Time on page and scroll depth of the current page view, sent when the page is hidden or left. A page view
  // can report again after being shown again; e0/sp0 tell the collector what was already reported.
  var current, visibleMs = 0, visibleSince = null, maxScroll = 0;
  function scrollDepth() {
    var el = d.documentElement;
    var h = Math.max(el.scrollHeight, d.body ? d.body.scrollHeight : 0) - w.innerHeight;
    if (h <= 0) return 100;
    return Math.min(100, Math.max(0, Math.round(((w.pageYOffset || el.scrollTop || 0) / h) * 100)));
  }
  // leaving: an SPA navigation, where the DOM may already show the next page, so only what was measured on
  // this page (and its skip tag, sampled after it rendered) is used.
  function report(leaving) {
    try {
      if (!current) return;
      var ms = visibleMs + (visibleSince === null ? 0 : Date.now() - visibleSince);
      if (ms === 0) return; // never visible
      var e = Math.min(86400, Math.round(ms / 1000));
      var sp = maxScroll = Math.max(maxScroll, current.sp, leaving ? 0 : scrollDepth());
      if (e <= current.e && sp <= current.sp) return;
      send('engagement', { u: current.u, e: e, sp: sp, e0: current.e || undefined, sp0: current.sp || undefined }, current.p,
        leaving ? current.skip : undefined);
      current.e = e;
      current.sp = sp;
    } catch (err) { /* ignore */ }
  }

  var lastPath;
  // deferred: after a history change, wait until the router has rendered the new page (title, meta tags).
  function enter(deferred) {
    var path = loc.pathname;
    if (path === lastPath) return;
    report(deferred);
    lastPath = path;
    var url = pageUrl();
    var page = current = { u: url, p: path, e: 0, sp: 0, skip: false };
    visibleMs = 0;
    visibleSince = d.visibilityState === 'hidden' ? null : Date.now();
    maxScroll = 0;
    var go = function () {
      // The page has rendered: sample its depth (a page that fits the screen is 100%) and its skip tag.
      if (current === page) {
        try { maxScroll = Math.max(maxScroll, scrollDepth()); page.skip = meta('skip') != null; } catch (err) { /* ignore */ }
      }
      if (!pageviews) return;
      var type = meta('type');
      send('pageview', {
        u: url,
        r: d.referrer || undefined,
        pt: text(d.title, 200),
        py: type && /^[a-z0-9_-]{1,40}$/.test(type) ? type : undefined,
        pg: text(meta('group'), 120)
      }, path);
    };
    if (deferred) w.setTimeout(go, 0); else go();
  }
  function navigated() { enter(true); }

  function track(name, options) {
    options = options || {};
    send('event', { n: String(name), l: text(options.label, 120), g: targetUrl(options.target) });
  }

  function onClick(e) {
    try {
      var el = e.target && e.target.closest ? e.target : null;
      if (!el) return;
      if (e.type === 'click' ? e.button !== 0 : e.button !== 1) return;
      var tagged = e.type === 'click' && el.closest('[data-stats-event]');
      if (tagged) {
        var ta = tagged.closest('a[href]');
        send('event', {
          n: tagged.getAttribute('data-stats-event'),
          l: text(tagged.getAttribute('data-stats-label'), 120),
          g: ta && /^https?:$/.test(ta.protocol) ? linkUrl(ta) : undefined
        });
      }
      var a = el.closest('a[href]');
      if (!a || !/^https?:$/.test(a.protocol)) return;
      var file = a.pathname.split('/').pop();
      var dot = file.lastIndexOf('.');
      var link = { g: linkUrl(a), l: labelOf(a) };
      if (dot > 0 && exts.indexOf(file.slice(dot + 1).toLowerCase()) >= 0) {
        send('download', link);
      } else if (a.hostname !== loc.hostname) {
        send('outbound', link);
      }
    } catch (err) { /* ignore */ }
  }

  ['pushState', 'replaceState'].forEach(function (method) {
    var original = w.history[method];
    w.history[method] = function () {
      var result = original.apply(this, arguments);
      navigated();
      return result;
    };
  });
  w.addEventListener('popstate', navigated);
  d.addEventListener('click', onClick, true);
  d.addEventListener('auxclick', onClick, true);
  w.addEventListener('scroll', function () {
    try { var sp = scrollDepth(); if (sp > maxScroll) maxScroll = sp; } catch (err) { /* ignore */ }
  }, { passive: true });
  d.addEventListener('visibilitychange', function () {
    if (d.visibilityState === 'hidden') {
      if (visibleSince !== null) { visibleMs += Date.now() - visibleSince; visibleSince = null; }
      report();
    } else if (visibleSince === null) {
      visibleSince = Date.now();
    }
  });
  w.addEventListener('pagehide', report);

  var queue = (w.n2nStats && w.n2nStats.q) || [];
  w.n2nStats = track;
  enter(false);
  for (var i = 0; i < queue.length; i++) track.apply(null, queue[i]);
})(window, document);
