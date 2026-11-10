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

  function send(type, extra) {
    try {
      if (blocked || !site) return;
      var path = loc.pathname;
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

  var lastPath;
  function pageview() {
    if (loc.pathname === lastPath) return;
    lastPath = loc.pathname;
    send('pageview', { r: d.referrer || undefined });
  }

  function track(name, options) {
    send('event', { n: String(name), l: options && options.label != null ? String(options.label) : undefined });
  }

  function onClick(e) {
    try {
      var el = e.target && e.target.closest ? e.target : null;
      if (!el) return;
      if (e.type === 'click' ? e.button !== 0 : e.button !== 1) return;
      var tagged = e.type === 'click' && el.closest('[data-stats-event]');
      if (tagged) track(tagged.getAttribute('data-stats-event'), { label: tagged.getAttribute('data-stats-label') });
      var a = el.closest('a[href]');
      if (!a || !/^https?:$/.test(a.protocol)) return;
      var file = a.pathname.split('/').pop();
      var dot = file.lastIndexOf('.');
      if (dot > 0 && exts.indexOf(file.slice(dot + 1).toLowerCase()) >= 0) {
        send('download', { l: linkUrl(a) });
      } else if (a.hostname !== loc.hostname) {
        send('outbound', { l: linkUrl(a) });
      }
    } catch (err) { /* ignore */ }
  }

  ['pushState', 'replaceState'].forEach(function (method) {
    var original = w.history[method];
    w.history[method] = function () {
      var result = original.apply(this, arguments);
      pageview();
      return result;
    };
  });
  w.addEventListener('popstate', pageview);
  d.addEventListener('click', onClick, true);
  d.addEventListener('auxclick', onClick, true);

  var queue = (w.n2nStats && w.n2nStats.q) || [];
  w.n2nStats = track;
  pageview();
  for (var i = 0; i < queue.length; i++) track.apply(null, queue[i]);
})(window, document);
