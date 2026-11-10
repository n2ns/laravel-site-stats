import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM, VirtualConsole } from 'jsdom';

const source = readFileSync(new URL('../resources/js/stats.js', import.meta.url), 'utf8');

/**
 * Loads stats.js into a fresh page. Returns the window and the list of sent payloads.
 * options: url, attrs (script attributes), body, webdriver, beacon (false = unavailable, 'refuse' = returns false), before(window)
 */
function load(options = {}) {
  const attrs = { 'data-site': 'SITE1', src: 'https://stats.example.com/js/stats.js?v=1', ...options.attrs };
  const scriptTag = '<script defer ' + Object.entries(attrs).map(([k, v]) => `${k}="${v}"`).join(' ') + '></script>';
  const dom = new JSDOM(`<!doctype html><html><head>${scriptTag}</head><body>${options.body ?? ''}</body></html>`, {
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
  return { window, sent, storageTouches, payloads: () => sent.map((s) => s.data) };
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

test('counts SPA navigations once per pathname change', () => {
  const { window, payloads } = load();
  window.history.pushState({}, '', '/about');
  window.history.pushState({}, '', '/about?tab=2');
  window.history.replaceState({}, '', '/pricing');
  window.history.pushState({}, '', '/pricing#faq');
  window.dispatchEvent(new window.PopStateEvent('popstate'));
  assert.deepEqual(payloads().map((p) => [p.t, p.u]), [
    ['pageview', 'https://www.example.com/start?utm_source=news'],
    ['pageview', 'https://www.example.com/about'],
    ['pageview', 'https://www.example.com/pricing'],
  ]);
});

test('popstate to a different path sends a pageview', () => {
  const { window, payloads } = load();
  window.history.pushState({}, '', '/a');
  // Simulate the browser restoring /start without going through pushState.
  Object.getPrototypeOf(window.history).replaceState.call(window.history, {}, '', '/start');
  window.dispatchEvent(new window.PopStateEvent('popstate'));
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
  assert.deepEqual(events.map((p) => [p.t, p.l]), [
    ['outbound', 'https://other.org/page'],
    ['outbound', 'https://other.org/page'],
  ]);
  assert.equal('r' in events[0], false);
});

test('download links on any host send download with the absolute href', () => {
  const { window, payloads } = load({
    body: '<a id="pdf" href="/files/Guide.PDF">pdf</a><a id="ext" href="https://cdn.other.org/app.apk">apk</a>'
      + '<a id="html" href="/files/page.html">html</a>',
  });
  click(window, '#pdf');
  click(window, '#ext', 1);
  click(window, '#html');
  assert.deepEqual(payloads().slice(1).map((p) => [p.t, p.l]), [
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
  assert.deepEqual(payloads().slice(1).map((p) => [p.t, p.l]), [
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

test('data-exclude-paths with * wildcards suppresses matching pages', () => {
  const { window, payloads } = load({
    url: 'https://www.example.com/admin/users',
    attrs: { 'data-exclude-paths': '/admin/*, /preview' },
  });
  assert.equal(payloads().length, 0);
  window.history.pushState({}, '', '/preview');
  window.history.pushState({}, '', '/previews');
  window.history.pushState({}, '', '/blog/admin/x');
  assert.deepEqual(payloads().map((p) => new URL(p.u).pathname), ['/previews', '/blog/admin/x']);
});

test('never touches cookies, localStorage or sessionStorage', () => {
  const { window, storageTouches, sent } = load({ body: '<a id="o" href="https://other.org/x.zip">o</a>' });
  click(window, '#o');
  window.history.pushState({}, '', '/next');
  window.n2nStats('evt');
  assert.equal(sent.length, 4);
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
