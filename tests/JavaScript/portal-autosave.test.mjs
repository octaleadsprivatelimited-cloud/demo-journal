import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';

const code = readFileSync(new URL('../../resources/js/portal.js', import.meta.url), 'utf8');

function setup(responses) {
    const handlers = new Map();
    const timers = new Map();
    const requests = [];
    const errors = [];
    const fields = {
        title: { value: 'Original title' },
        updated_at: { value: '2026-10-30T00:00:00.000000Z' },
        draft_version: { value: 'a'.repeat(64) },
    };
    const status = { classList: { add() {}, remove() {} }, style: {}, textContent: '' };
    const form = {
        dataset: { autosave: '/author/articles/original/autosave' },
        querySelector(selector) {
            if (selector === '[data-autosave-status]') return status;
            return fields[selector.match(/name="([^"]+)"/)?.[1]] ?? null;
        },
        querySelectorAll() { return []; },
        addEventListener(type, callback) { handlers.set(type, callback); },
    };
    class FakeFormData {
        entries() { return Object.entries(fields).map(([name, field]) => [name, field.value]); }
    }
    const context = {
        document: {
            querySelector(selector) {
                if (selector === 'form[data-autosave]') return form;
                if (selector === 'meta[name="csrf-token"]') return { content: 'test-token' };
                return null;
            },
            querySelectorAll() { return []; },
            addEventListener() {},
        },
        FormData: FakeFormData,
        File: class {},
        AbortSignal: { timeout() { return undefined; } },
        setTimeout(callback) { const id = timers.size + 1; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
        addEventListener() {},
        JournalErrors: { show(value) { errors.push(value); } },
        async fetch(url, options) {
            requests.push({ url, ...options, data: JSON.parse(options.body) });
            const response = responses.shift();
            return { status: response.status, ok: response.status === 200, async json() { return response.body; } };
        },
    };
    context.window = context;
    vm.runInNewContext(code, context);
    return {
        fields, requests, errors, status,
        async edit(title) {
            fields.title.value = title;
            handlers.get('input')({ target: { name: 'title' } });
            const callbacks = [...timers.values()];
            timers.clear();
            callbacks.forEach(callback => callback());
            await new Promise(resolve => setImmediate(resolve));
        },
    };
}

test('autosave submits the current version and adopts the new version after success', async () => {
    const nextVersion = 'b'.repeat(64);
    const page = setup([
        { status: 200, body: { updated_at: '2026-10-30T00:00:00.000000Z', draft_version: nextVersion } },
        { status: 200, body: { updated_at: '2026-10-30T00:00:01.000000Z', draft_version: 'c'.repeat(64) } },
    ]);
    await page.edit('First edit');
    assert.equal(page.requests[0].data.draft_version, 'a'.repeat(64));
    assert.equal(page.fields.draft_version.value, nextVersion);
    await page.edit('Second edit');
    assert.equal(page.requests[1].data.draft_version, nextVersion);
    assert.equal(page.requests[1].headers['X-CSRF-TOKEN'], 'test-token');
});

test('a version conflict preserves the local token and stops further autosaves', async () => {
    const page = setup([
        { status: 409, body: { code: 'version_conflict', message: 'Reload before saving.', draft_version: 'b'.repeat(64) } },
    ]);
    await page.edit('Unsaved edit');
    assert.equal(page.fields.draft_version.value, 'a'.repeat(64));
    assert.equal(page.errors[0][0].code, 'version_conflict');
    await page.edit('Another unsaved edit');
    assert.equal(page.requests.length, 1);
});
