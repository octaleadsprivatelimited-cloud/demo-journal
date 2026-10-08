import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';
const code = readFileSync(new URL('../../resources/js/public-analytics.js', import.meta.url), 'utf8');
function setup(saved = null) {
    const nodes = Object.fromEntries(['consent', 'accept', 'reject', 'preferences'].map(x => [x, { hidden: true, handlers: {}, addEventListener(k, fn) { this.handlers[k] = fn; }, focus() {} }]));
    const scripts = [], store = new Map(saved ? [['sjc.analytics.consent.v1', JSON.stringify(saved)]] : []);
    const document = {
        title: 'Cardiology research', referrer: 'https://example.com/private?email=private@example.com', cookie: '', handlers: {},
        getElementById() { return { textContent: JSON.stringify({ ga: 'G-TEST123456', page: 'https://sjcjournal.com/' }) }; },
        querySelector(selector) { return nodes[selector.match(/analytics-(.*)\]/)[1]]; },
        createElement() { return {}; }, head: { appendChild(tag) { scripts.push(tag); } },
        addEventListener(k, fn) { this.handlers[k] = fn; }
    };
    const location = { hostname: 'sjcjournal.com', origin: 'https://sjcjournal.com', href: 'https://sjcjournal.com/', reload() { this.reloaded = true; } };
    const context = { document, location, URL, Date, localStorage: { getItem(k) { return store.get(k) ?? null; }, setItem(k, v) { store.set(k, v); } } };
    context.window = context;
    vm.runInNewContext(code, context);
    return { context, nodes, scripts, document, location, store };
}
test('no network tags until consent, one page view after accepting, origin-only referrer', () => {
    const x = setup();
    assert.equal(x.scripts.length, 0); assert.equal(x.nodes.consent.hidden, false);
    x.nodes.accept.handlers.click(); x.nodes.accept.handlers.click();
    assert.equal(x.scripts.length, 1);
    const events = x.context.dataLayer.map(a => [...a]);
    assert.equal(events.filter(a => a[1] === 'page_view').length, 1);
    assert.equal(events.find(a => a[1] === 'page_view')[2].page_referrer, 'https://example.com/');
    assert.equal(events[0][2].ad_storage, 'denied');
});
test('necessary-only stays off, later acceptance works, withdrawal disables GA and reloads', () => {
    const x = setup(); x.nodes.reject.handlers.click(); assert.equal(x.scripts.length, 0);
    x.nodes.accept.handlers.click(); assert.equal(x.context['ga-disable-G-TEST123456'], false);
    x.nodes.reject.handlers.click(); assert.equal(x.context['ga-disable-G-TEST123456'], true);
    assert.equal(x.location.reloaded, true);
});
test('saved denial stays off and expired consent requires another choice', () => {
    const x = setup({ accepted: false, at: Date.now() }); assert.equal(x.scripts.length, 0); assert.equal(x.nodes.consent.hidden, true);
    const expired = setup({ accepted: true, at: 0 }); assert.equal(expired.scripts.length, 0); assert.equal(expired.nodes.consent.hidden, false);
});
test('PDF download excludes query parameters and ignores private or external links', () => {
    const x = setup({ accepted: true, at: Date.now() });
    const click = href => x.document.handlers.click({ target: { closest() { return { href }; } } });
    click('https://sjcjournal.com/article/heart-failure/pdf?token=secret');
    click('https://sjcjournal.com/author/submissions/1/file'); click('https://example.com/private.pdf');
    const downloads = x.context.dataLayer.map(a => [...a]).filter(a => a[1] === 'file_download');
    assert.equal(downloads.length, 1); assert.equal(downloads[0][2].link_url, 'https://sjcjournal.com/article/heart-failure/pdf');
});
