import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';

const publicCode = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const portalCode = readFileSync(new URL('../../resources/js/portal.js', import.meta.url), 'utf8');

function setup(portal) {
    const documentHandlers = new Map();
    const attributes = new Map();
    const clickHandlers = [];
    const button = {
        addEventListener(type, callback) { if (type === 'click') clickHandlers.push(callback); },
        setAttribute(name, value) { attributes.set(name, value); },
        getAttribute(name) { return attributes.get(name); },
        contains(target) { return target === this; },
        focus() {},
    };
    button.setAttribute('aria-expanded', 'false');
    const menu = {
        hidden: true,
        setAttribute(name) { if (name === 'hidden') this.hidden = true; },
        removeAttribute(name) { if (name === 'hidden') this.hidden = false; },
        hasAttribute(name) { return name === 'hidden' && this.hidden; },
        contains(target) { return target === this; },
    };
    const selectors = new Map([
        ['[data-account-toggle]', button],
        ['[data-account-menu]', menu],
    ]);
    const header = {
        querySelector(selector) { return selectors.get(selector) ?? null; },
        querySelectorAll() { return []; },
        contains(target) { return target === button || target === menu; },
        style: { setProperty() {} },
        classList: { toggle() {} },
    };
    if (!portal) selectors.set('[data-site-header]', header);
    const document = {
        readyState: 'complete',
        querySelector(selector) { return selectors.get(selector) ?? null; },
        querySelectorAll() { return []; },
        addEventListener(type, callback) {
            if (!documentHandlers.has(type)) documentHandlers.set(type, []);
            documentHandlers.get(type).push(callback);
        },
    };
    class Field {}
    const context = {
        document,
        HTMLInputElement: Field, HTMLTextAreaElement: Field, HTMLSelectElement: Field,
        setTimeout() { return 1; }, clearTimeout() {},
        addEventListener() {},
        matchMedia() { return { matches: false, addEventListener() {} }; },
        scrollY: 0,
    };
    context.window = context;
    vm.createContext(context);
    // The portal's inline script executes before the deferred Vite module.
    if (portal) vm.runInContext(portalCode, context);
    vm.runInContext(publicCode, context);
    const dispatch = (type, event) => {
        for (const callback of documentHandlers.get(type) ?? []) callback(event);
    };
    return {
        button, menu, dispatch,
        click() {
            const event = { target: button, stopped: false, stopPropagation() { this.stopped = true; } };
            // stopPropagation does not suppress other listeners on the same button.
            for (const callback of clickHandlers) callback(event);
            if (!event.stopped) dispatch('click', event);
        },
    };
}

test('portal account menu works when the shared public bundle is also loaded', () => {
    const page = setup(true);
    page.click();
    assert.equal(page.menu.hidden, false);
    assert.equal(page.button.getAttribute('aria-expanded'), 'true');
    page.click();
    assert.equal(page.menu.hidden, true);
    page.click();
    page.dispatch('keydown', { key: 'Escape', target: page.button });
    assert.equal(page.menu.hidden, true);
    assert.equal(page.button.getAttribute('aria-expanded'), 'false');
});

test('public account menu still opens and closes on an outside click', () => {
    const page = setup(false);
    page.click();
    assert.equal(page.menu.hidden, false);
    assert.equal(page.button.getAttribute('aria-expanded'), 'true');
    page.dispatch('click', { target: {} });
    assert.equal(page.menu.hidden, true);
    assert.equal(page.button.getAttribute('aria-expanded'), 'false');
});
