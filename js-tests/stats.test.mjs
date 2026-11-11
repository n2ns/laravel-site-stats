import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM, VirtualConsole } from 'jsdom';

const source = readFileSync(new URL('../resources/js/stats.js', import.meta.url), 'utf8');

/** Lets timers queued by the script (deferred SPA pageviews) run. */
const flush = (window) => new Promise((resolve) => window.setTimeout(resolve, 5));

/**
 * Loads stats.js into a fresh page. Returns the window and the list of sent payloads.
 * options: url, attrs (script attributes), head, body, webdriver, beacon (false = unavailable, 'refuse' = returns false), before(window)
 */
function load(options = {}) {
  const attrs = { 'data-site': 'SITE1', src: 'https://stats.example.com/js/stats.js?v=1', ...options.attrs };
  const scriptTag = '<script defer ' + Object.entries(attrs).map(([k, v]) => `${k}="${v}"`).join(' ') + '></script>';
  const dom = new JSDOM(`<!doctype html><html><head>${options.head ?? ''}${scriptTag}</head><body>${options.body ?? ''}</body></html>`, {
    url: options.url ?? 'https://www.example.com/start?utm_source=news',
    referrer: (options.referrer ?? 'https://google.com/') || undefined,
    runScripts: 'outside-only',
    virtualConsole: new VirtualConsole(),
  });
  const window = dom.window;
  const sent = [];
  const storageTouches = [];

  window.Blob = class {
    constructor(parts, opts) { this.parts = parts; this.type = opts.type; }
  };
  if (options.beacon === false) {
    Object.defineProperty(window.navigator, 'sendBeacon', { value: undefined, configurable: true });
  } else {
    Object.defineProperty(window.navigator, 'sendBeacon', {
      configurable: true,
      value(url, blob) {
        assert.equal(blob.type, 'text/plain');
        sent.push({ via: 'beacon', url, data: JSON.parse(blob.parts.join('')) });
        return options.beacon !== 'refuse';
      },
    });
  }
  window.fetch = (url, init) => {
    sent.push({ via: 'fetch', url, init, data: JSON.parse(init.body) });
    return Promise.resolve({});
  };
  if (options.webdriver) {
    Object.defineProperty(window.navigator, 'webdriver', { value: true, configurable: true });
  }
  for (const name of ['localStorage', 'sessionStorage']) {
    Object.defineProperty(window, name, { configurable: true, get() { storageTouches.push(name); return undefined; } });
  }
  Object.defineProperty(window.document, 'cookie', {
    configurable: true,
    get() { storageTouches.push('cookie'); return ''; },
    set() { storageTouches.push('cookie'); },
  });
  // Stop jsdom from attempting navigation on link clicks.
  window.document.addEventListener('click', (e) => e.preventDefault());
  window.document.addEventListener('auxclick', (e) => e.preventDefault());

  options.before?.(window);
  window.eval(source);
  return {
    window, sent, storageTouches,
    // Pageviews and events; engagement reports (time on page, scroll depth) separately.
    payloads: () => sent.map((s) => s.data).filter((p) => p.t !== 'engagement'),
    engagements: () => sent.map((s) => s.data).filter((p) => p.t === 'engagement'),
  };
}

function click(window, selector, button = 0) {
  const el = window.document.querySelector(selector);
  el.dispatchEvent(new window.MouseEvent(button === 0 ? 'click' : 'auxclick', { bubbles: true, cancelable: true, button }));
}

test('sends a pageview on load with the §5.1 payload to the endpoint derived from src', () => {
  const { sent } = load();
  assert.equal(sent.length, 1);
  assert.equal(sent[0].via, 'beacon');
  assert.equal(sent[0].url, 'https://stats.example.com/api/event');
  assert.deepEqual(Object.keys(sent[0].data).sort(), ['r', 's', 't', 'u', 'w']);
  assert.equal(sent[0].data.s, 'SITE1');
  assert.equal(sent[0].data.t, 'pageview');
  assert.equal(sent[0].data.u, 'https://www.example.com/start?utm_source=news');
  assert.equal(sent[0].data.r, 'https://google.com/');
  assert.equal(typeof sent[0].data.w, 'number');
});

test('sends only the path and attribution parameters, never the fragment or other query values', () => {
  const { payloads } = load({ url: 'https://www.example.com/reset?token=secret&utm_source=news&gclid=abc&email=a%40b.c#access_token=xyz' });
  assert.equal(payloads()[0].u, 'https://www.example.com/reset?utm_source=news&gclid=abc');
});

test('omits r when there is no referrer', () => {
  const { payloads } = load({ referrer: '' });
  assert.equal('r' in payloads()[0], false);
});

test('keeps the path prefix before /js/stats.js and honours data-api', () => {
  assert.equal(load({ attrs: { src: 'https://a.example.com/stats/js/stats.js' } }).sent[0].url, 'https://a.example.com/stats/api/event');
  assert.equal(load({ attrs: { 'data-api': 'https://collect.example.net/e' } }).sent[0].url, 'https://collect.example.net/e');
});

test('counts SPA navigations once per pathname change', async () => {
  const { window, payloads } = load();
  window.history.pushState({}, '', '/about');
  window.history.pushState({}, '', '/about?tab=2');
  window.history.replaceState({}, '', '/pricing');
  window.history.pushState({}, '', '/pricing#faq');
  window.dispatchEvent(new window.PopStateEvent('popstate'));
  await flush(window);
  assert.deepEqual(payloads().map((p) => [p.t, p.u]), [
    ['pageview', 'https://www.example.com/start?utm_source=news'],
    ['pageview', 'https://www.example.com/about'],
    ['pageview', 'https://www.example.com/pricing'],
  ]);
});

test('popstate to a different path sends a pageview', async () => {
  const { window, payloads } = load();
  window.history.pushState({}, '', '/a');
  // Simulate the browser restoring /start without going through pushState.
  Object.getPrototypeOf(window.history).replaceState.call(window.history, {}, '', '/start');
  window.dispatchEvent(new window.PopStateEvent('popstate'));
  await flush(window);
  assert.deepEqual(payloads().map((p) => new URL(p.u).pathname), ['/start', '/a', '/start']);
});

test('outbound links: left and middle click send outbound, right click and same host do not', () => {
  const { window, payloads } = load({
    body: '<a id="out" href="https://other.org/page?x=1"><span id="inner">go</span></a>'
      + '<a id="same" href="/local">local</a><a id="mail" href="mailto:a@b.c">mail</a>',
  });
  click(window, '#inner', 0);
  click(window, '#out', 1);
  window.document.querySelector('#out').dispatchEvent(new window.MouseEvent('auxclick', { bubbles: true, button: 2 }));
  click(window, '#same', 0);
  click(window, '#mail', 0);
  const events = payloads().slice(1);
  assert.deepEqual(events.map((p) => [p.t, p.g]), [
    ['outbound', 'https://other.org/page'],
    ['outbound', 'https://other.org/page'],
  ]);
  assert.equal('r' in events[0], false);
  assert.equal('l' in events[0], false);
});

test('download links on any host send download with the absolute href', () => {
  const { window, payloads } = load({
    body: '<a id="pdf" href="/files/Guide.PDF">pdf</a><a id="ext" href="https://cdn.other.org/app.apk">apk</a>'
      + '<a id="html" href="/files/page.html">html</a>',
  });
  click(window, '#pdf');
  click(window, '#ext', 1);
  click(window, '#html');
  assert.deepEqual(payloads().slice(1).map((p) => [p.t, p.g]), [
    ['download', 'https://www.example.com/files/Guide.PDF'],
    ['download', 'https://cdn.other.org/app.apk'],
  ]);
});

test('data-download-extensions overrides the built-in list', () => {
  const { window, payloads } = load({
    attrs: { 'data-download-extensions': 'epub, .html' },
    body: '<a id="pdf" href="/a.pdf">pdf</a><a id="epub" href="/b.epub">epub</a><a id="html" href="/c.html">c</a>',
  });
  click(window, '#pdf');
  click(window, '#epub');
  click(window, '#html');
  assert.deepEqual(payloads().slice(1).map((p) => [p.t, p.g]), [
    ['download', 'https://www.example.com/b.epub'],
    ['download', 'https://www.example.com/c.html'],
  ]);
});

test('built-in download list matches config/stats.php', () => {
  const config = readFileSync(new URL('../config/stats.php', import.meta.url), 'utf8');
  const block = config.match(/'download_extensions' => \[([^\]]*)\]/)[1];
  const fromConfig = [...block.matchAll(/'([^']+)'/g)].map((m) => m[1]).join(',');
  const fromScript = source.match(/'(pdf,[a-z0-9,]+)'/)[1];
  assert.equal(fromScript, fromConfig);
});

test('data-stats-event elements send custom events with optional label', () => {
  const { window, payloads } = load({
    body: '<button data-stats-event="signup" data-stats-label="hero"><b id="b1">Sign up</b></button>'
      + '<div id="d2" data-stats-event="open_menu">menu</div>',
  });
  click(window, '#b1');
  click(window, '#d2');
  click(window, '#d2', 1);
  const events = payloads().slice(1);
  assert.deepEqual(events.map((p) => [p.t, p.n, p.l]), [
    ['event', 'signup', 'hero'],
    ['event', 'open_menu', undefined],
  ]);
  assert.equal('l' in events[1], false);
});

test('manual API and calls queued before the script loaded', () => {
  const { window, payloads } = load({
    before(w) {
      w.eval('window.n2nStats = window.n2nStats || function(){(window.n2nStats.q=window.n2nStats.q||[]).push(arguments)};'
        + 'n2nStats("early", {label: "q1"}); n2nStats("early2");');
    },
  });
  window.n2nStats('later', { label: 42 });
  assert.deepEqual(payloads().map((p) => [p.t, p.n, p.l]), [
    ['pageview', undefined, undefined],
    ['event', 'early', 'q1'],
    ['event', 'early2', undefined],
    ['event', 'later', '42'],
  ]);
});

test('nothing is sent under webdriver, on localhost, 127.0.0.1 or file://', () => {
  assert.equal(load({ webdriver: true }).sent.length, 0);
  assert.equal(load({ url: 'http://localhost:8000/' }).sent.length, 0);
  assert.equal(load({ url: 'http://127.0.0.1/' }).sent.length, 0);
  assert.equal(load({ url: 'file:///tmp/page.html' }).sent.length, 0);
  const { window, sent } = load({ webdriver: true, body: '<a id="o" href="https://other.org/">o</a>' });
  click(window, '#o');
  window.n2nStats('x');
  assert.equal(sent.length, 0);
});

test('data-exclude-paths with * wildcards suppresses matching pages', async () => {
  const { window, payloads } = load({
    url: 'https://www.example.com/admin/users',
    attrs: { 'data-exclude-paths': '/admin/*, /preview' },
  });
  assert.equal(payloads().length, 0);
  window.history.pushState({}, '', '/preview');
  window.history.pushState({}, '', '/previews');
  window.history.pushState({}, '', '/blog/admin/x');
  await flush(window);
  assert.deepEqual(payloads().map((p) => new URL(p.u).pathname), ['/previews', '/blog/admin/x']);
});

test('never touches cookies, localStorage or sessionStorage', async () => {
  const { window, storageTouches, sent } = load({ body: '<a id="o" href="https://other.org/x.zip">o</a>' });
  click(window, '#o');
  window.history.pushState({}, '', '/next');
  window.n2nStats('evt');
  await flush(window);
  // pageview, download, pageview, event (plus the first page's engagement report when any time passed)
  assert.equal(sent.filter((x) => x.data.t !== 'engagement').length, 4);
  assert.deepEqual(storageTouches, []);
});

test('falls back to fetch when sendBeacon is missing or refuses', () => {
  for (const beacon of [false, 'refuse']) {
    const { sent } = load({ beacon });
    const last = sent.at(-1);
    assert.equal(last.via, 'fetch');
    assert.equal(last.url, 'https://stats.example.com/api/event');
    assert.deepEqual(
      JSON.parse(JSON.stringify({ ...last.init, body: undefined })),
      { method: 'POST', keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } },
    );
    assert.equal(last.data.t, 'pageview');
  }
});

test('swallows transport errors', () => {
  const { window } = load({
    before(w) {
      Object.defineProperty(w.navigator, 'sendBeacon', { value() { throw new Error('boom'); }, configurable: true });
      w.fetch = () => Promise.reject(new Error('offline'));
    },
  });
  assert.doesNotThrow(() => window.n2nStats('x'));
});

test('pageviews carry the title, page type and content group read at send time', async () => {
  const { window, payloads } = load({
    head: '<title>  Home \n page  </title><meta name="n2n-stats:type" content="landing">'
      + '<meta name="n2n-stats:group" content="  Guides  ">',
  });
  const first = payloads()[0];
  assert.equal(first.pt, 'Home page');
  assert.equal(first.py, 'landing');
  assert.equal(first.pg, 'Guides');

  const doc = window.document;
  doc.title = 'x'.repeat(250);
  doc.querySelector('meta[name="n2n-stats:type"]').setAttribute('content', 'blog_post-2');
  doc.querySelector('meta[name="n2n-stats:group"]').setAttribute('content', 'g'.repeat(130));
  window.history.pushState({}, '', '/post');
  await flush(window);
  const second = payloads()[1];
  assert.equal(second.pt, 'x'.repeat(200));
  assert.equal(second.py, 'blog_post-2');
  assert.equal(second.pg, 'g'.repeat(120));

  doc.title = '';
  doc.querySelector('meta[name="n2n-stats:type"]').setAttribute('content', 'Bad Type');
  doc.querySelector('meta[name="n2n-stats:group"]').remove();
  window.history.pushState({}, '', '/other');
  await flush(window);
  const third = payloads()[2];
  assert.deepEqual(Object.keys(third).sort(), ['r', 's', 't', 'u', 'w']);
});

test('page type longer than 40 characters is not sent; cuts never split a surrogate pair', () => {
  const { payloads } = load({
    head: `<title>${'a'.repeat(199)}\u{1F600}</title><meta name="n2n-stats:type" content="${'a'.repeat(41)}">`,
  });
  assert.equal(payloads()[0].pt, 'a'.repeat(199));
  assert.equal('py' in payloads()[0], false);
});

test('n2n-stats:skip suppresses pageviews and events, re-checked at send time', async () => {
  const { window, payloads } = load({
    head: '<meta name="n2n-stats:skip">',
    body: '<a id="o" href="https://other.org/">o</a><button id="b" data-stats-event="cta">b</button>',
  });
  click(window, '#o');
  click(window, '#b');
  window.n2nStats('manual');
  assert.equal(payloads().length, 0);

  window.document.querySelector('meta[name="n2n-stats:skip"]').remove();
  window.history.pushState({}, '', '/found');
  await flush(window);
  click(window, '#o');
  assert.deepEqual(payloads().map((p) => p.t), ['pageview', 'outbound']);
  assert.equal(new URL(payloads()[0].u).pathname, '/found');

  const meta = window.document.createElement('meta');
  meta.setAttribute('name', 'n2n-stats:skip');
  window.document.head.append(meta);
  window.history.pushState({}, '', '/missing');
  window.n2nStats('manual');
  await flush(window);
  assert.equal(payloads().length, 2);
});

test('outbound and download links send the closest data-stats-label as l', () => {
  const { window, payloads } = load({
    body: '<div data-stats-label="project-42"><p><a id="o" href="https://other.org/p?x=1">o</a>'
      + '<a id="f" href="/files/a.zip">f</a></p></div>'
      + `<a id="long" data-stats-label="${'L'.repeat(130)}" href="https://other.org/l">l</a>`,
  });
  click(window, '#o');
  click(window, '#f');
  click(window, '#long');
  assert.deepEqual(payloads().slice(1).map((p) => [p.t, p.g, p.l]), [
    ['outbound', 'https://other.org/p', 'project-42'],
    ['download', 'https://www.example.com/files/a.zip', 'project-42'],
    ['outbound', 'https://other.org/l', 'L'.repeat(120)],
  ]);
});

test('data-stats-event on or inside an http(s) link also sends the link as g, internal links included', () => {
  const { window, payloads } = load({
    body: '<a href="/checkout?plan=pro#x"><span id="inside" data-stats-event="buy" data-stats-label="pro">Buy</span></a>'
      + '<a id="ext" href="https://other.org/docs" data-stats-event="docs">docs</a>'
      + '<a href="mailto:a@b.c"><span id="mail" data-stats-event="mail">mail</span></a>'
      + '<button id="plain" data-stats-event="plain">p</button>',
  });
  click(window, '#inside');
  click(window, '#ext');
  click(window, '#mail');
  click(window, '#plain');
  assert.deepEqual(payloads().slice(1).map((p) => [p.t, p.n, p.l, p.g]), [
    ['event', 'buy', 'pro', 'https://www.example.com/checkout'],
    ['event', 'docs', undefined, 'https://other.org/docs'],
    ['outbound', undefined, undefined, 'https://other.org/docs'],
    ['event', 'mail', undefined, undefined],
    ['event', 'plain', undefined, undefined],
  ]);
  assert.equal('g' in payloads().at(-1), false);
});

test('manual API target is resolved against the page and reduced to scheme://host/path', () => {
  const { window, payloads } = load({
    url: 'https://www.example.com/shop/list?utm_source=x',
    before(w) {
      w.eval('window.n2nStats = window.n2nStats || function(){(window.n2nStats.q=window.n2nStats.q||[]).push(arguments)};'
        + 'n2nStats("queued", {label: "q", target: "/checkout?step=1#top"});');
    },
  });
  window.n2nStats('rel', { target: 'item/7?ref=a' });
  window.n2nStats('abs', { label: 'x', target: 'https://pay.other.org/a/b?c=d' });
  window.n2nStats('bad', { target: 'javascript:alert(1)' });
  window.n2nStats('long', { label: 'y'.repeat(200) });
  assert.deepEqual(payloads().slice(1).map((p) => [p.n, p.l, p.g]), [
    ['queued', 'q', 'https://www.example.com/checkout'],
    ['rel', undefined, 'https://www.example.com/shop/item/7'],
    ['abs', 'x', 'https://pay.other.org/a/b'],
    ['bad', undefined, undefined],
    ['long', 'y'.repeat(120), undefined],
  ]);
});

test('SPA pageviews read the title, meta tags and skip tag after the router has rendered the new page', async () => {
  const { window, payloads } = load({ head: '<title>A</title><meta name="n2n-stats:type" content="home">' });
  const doc = window.document;
  window.history.pushState({}, '', '/b');
  // A router renders the new route after pushState returns.
  doc.title = 'B';
  doc.querySelector('meta[name="n2n-stats:type"]').setAttribute('content', 'article');
  await flush(window);
  assert.equal(payloads()[1].u, 'https://www.example.com/b');
  assert.equal(payloads()[1].pt, 'B');
  assert.equal(payloads()[1].py, 'article');

  window.history.pushState({}, '', '/missing');
  doc.head.insertAdjacentHTML('beforeend', '<meta name="n2n-stats:skip">');
  await flush(window);
  assert.equal(payloads().length, 2);

  window.history.pushState({}, '', '/c');
  doc.querySelector('meta[name="n2n-stats:skip"]').remove();
  window.history.pushState({}, '', '/d');
  await flush(window);
  assert.deepEqual(payloads().slice(2).map((p) => p.u), ['https://www.example.com/c', 'https://www.example.com/d']);
});

test('data-pageviews="off" sends no pageviews but keeps events', async () => {
  const { window, payloads } = load({
    attrs: { 'data-pageviews': 'off' },
    body: '<a id="o" href="https://other.org/x">o</a>',
  });
  window.history.pushState({}, '', '/next');
  window.dispatchEvent(new window.PopStateEvent('popstate'));
  click(window, '#o');
  window.n2nStats('signup');
  await flush(window);
  assert.deepEqual(payloads().map((p) => p.t), ['outbound', 'event']);
});

/** Moves a jsdom page: document height, viewport height and scroll position. */
function layout(window, { height, viewport = 1000, scrolled = 0 }) {
  Object.defineProperty(window.document.documentElement, 'scrollHeight', { configurable: true, get: () => height });
  Object.defineProperty(window, 'innerHeight', { configurable: true, get: () => viewport });
  Object.defineProperty(window, 'pageYOffset', { configurable: true, get: () => scrolled });
}

function setVisibility(window, state) {
  Object.defineProperty(window.document, 'visibilityState', { configurable: true, get: () => state });
  window.document.dispatchEvent(new window.Event('visibilitychange'));
}

test('reports time on page and the deepest scroll when the page is hidden, and only the increase later', async () => {
  let now = 1_000_000;
  const { window, engagements } = load({ before(w) { w.Date.now = () => now; layout(w, { height: 5000 }); } });
  layout(window, { height: 5000, scrolled: 1000 });
  window.dispatchEvent(new window.Event('scroll'));
  layout(window, { height: 5000, scrolled: 0 });
  now += 42_000;
  setVisibility(window, 'hidden');
  assert.deepEqual(engagements(), [{ s: 'SITE1', t: 'engagement', u: 'https://www.example.com/start?utm_source=news', w: 0, e: 42, sp: 25 }]);

  now += 600_000; // hidden time does not count
  setVisibility(window, 'visible');
  now += 18_000;
  layout(window, { height: 5000, scrolled: 4000 });
  window.dispatchEvent(new window.Event('scroll'));
  window.dispatchEvent(new window.Event('pagehide'));
  assert.deepEqual(engagements()[1], { s: 'SITE1', t: 'engagement', u: 'https://www.example.com/start?utm_source=news', w: 0, e: 60, sp: 100, e0: 42, sp0: 25 });

  window.dispatchEvent(new window.Event('pagehide'));
  assert.equal(engagements().length, 2, 'nothing new, nothing sent');
});

test('reports the previous page before an SPA navigation, also with data-pageviews="off"', async () => {
  let now = 1_000_000;
  const { window, engagements, payloads } = load({ attrs: { 'data-pageviews': 'off' }, before(w) { w.Date.now = () => now; } });
  layout(window, { height: 3000, scrolled: 2000 });
  window.dispatchEvent(new window.Event('scroll'));
  now += 7_000;
  window.history.pushState({}, '', '/next');
  await flush(window);
  assert.deepEqual(engagements().map((e) => [e.u, e.e, e.sp]), [['https://www.example.com/start?utm_source=news', 7, 100]]);
  assert.equal(payloads().length, 0);
});


test('scroll depth never goes backwards within a page view', async () => {
  let now = 1_000_000;
  const { window, engagements } = load({ before(w) { w.Date.now = () => now; } });
  layout(window, { height: 500, viewport: 1000 }); // short while loading: fits the screen
  now += 5_000;
  setVisibility(window, 'hidden');
  layout(window, { height: 5000, scrolled: 800 }); // content loaded, scrolled 20%
  setVisibility(window, 'visible');
  now += 25_000;
  setVisibility(window, 'hidden');
  assert.deepEqual(engagements().map((e) => [e.e, e.sp, e.e0, e.sp0]), [[5, 100, undefined, undefined], [30, 100, 5, 100]]);
});

test('a page that was never visible reports nothing', async () => {
  const { window, engagements } = load({ before(w) { Object.defineProperty(w.document, 'visibilityState', { configurable: true, get: () => 'hidden' }); } });
  layout(window, { height: 500, viewport: 1000 });
  window.dispatchEvent(new window.Event('pagehide'));
  assert.equal(engagements().length, 0);
});

test('the report at an SPA navigation uses what was measured on the page being left', async () => {
  let now = 1_000_000;
  const { window, engagements } = load({ before(w) { w.Date.now = () => now; layout(w, { height: 5000 }); } });
  layout(window, { height: 5000, scrolled: 1000 });
  window.dispatchEvent(new window.Event('scroll')); // 25% on the old page
  now += 9_000;
  // The router swaps in a short new page (and its skip tag) before calling pushState.
  layout(window, { height: 500, viewport: 1000 });
  window.document.head.insertAdjacentHTML('beforeend', '<meta name="n2n-stats:skip">');
  window.history.pushState({}, '', '/new');
  assert.deepEqual(engagements().map((e) => [e.u, e.e, e.sp]), [['https://www.example.com/start?utm_source=news', 9, 25]]);
});
