import test from 'node:test';
import assert from 'node:assert/strict';
import { createScheduleIndex } from '../../resources/js/schedule-index.js';

async function browser(run) {
    const originals = Object.fromEntries(['window', 'document', 'fetch', 'DOMParser'].map(key => [key, globalThis[key]]));
    const timers = [];
    globalThis.window = {
        location: { href: 'https://dashboard.test/schedules?status=pending&month=2026-10&view=list&page=2' },
        history: { replaceState: (_state, _title, url) => { window.location.href = url.href; } },
        setInterval: callback => { timers.push(callback); return 1; }, clearInterval: id => timers.push(['clear', id]),
        setTimeout: () => 2, clearTimeout: () => {},
    };
    globalThis.document = { hidden: false, activeElement: {} };
    const results = { innerHTML: 'previous', contains: () => false, querySelectorAll: () => [] };
    const state = createScheduleIndex('list');
    state.$refs = { results };
    globalThis.DOMParser = class { parseFromString(html) { return { querySelector: () => ({ innerHTML: html }) }; } };
    try { await run(state, timers, results); }
    finally {
        for (const [key, value] of Object.entries(originals)) {
            if (value === undefined) delete globalThis[key];
            else globalThis[key] = value;
        }
    }
}

test('view change and refreshed request retain month, status and current page', () => browser(async (state, timers, results) => {
    state.init();
    state.setView('calendar');
    let calledUrl, options;
    globalThis.fetch = async (url, init) => {
        calledUrl = url; options = init;
        return { ok: true, redirected: false, text: async () => 'new executed status' };
    };
    await timers[0]();
    const url = new URL(calledUrl);
    assert.equal(url.searchParams.get('view'), 'calendar');
    assert.equal(url.searchParams.get('status'), 'pending');
    assert.equal(url.searchParams.get('month'), '2026-10');
    assert.equal(url.searchParams.get('page'), '2');
    assert.equal(options.cache, 'no-store');
    assert.equal(options.headers.Accept, 'text/html');
    assert.equal(results.innerHTML, 'new executed status');
    assert.equal(state.view, 'calendar');
    assert.equal(state.refreshError, false);
}));

test('refresh preserves last rows on a network failure or expired session and recovers later', () => browser(async (state, _timers, results) => {
    globalThis.fetch = async () => { throw new Error('offline'); };
    await state.refresh();
    assert.equal(results.innerHTML, 'previous');
    assert.equal(state.refreshError, true);
    globalThis.fetch = async () => ({ ok: true, redirected: true });
    await state.refresh();
    assert.equal(results.innerHTML, 'previous');
    globalThis.fetch = async () => ({ ok: true, text: async () => 'recovered' });
    await state.refresh();
    assert.equal(results.innerHTML, 'recovered');
    assert.equal(state.refreshError, false);
}));

test('hidden page and focused action never fetch or replace active controls', () => browser(async (state, _timers, results) => {
    globalThis.fetch = () => { throw new Error('must not fetch'); };
    document.hidden = true;
    await state.refresh();
    document.hidden = false;
    results.contains = () => true;
    await state.refresh();
    assert.equal(state.refreshError, false);
    assert.equal(results.innerHTML, 'previous');
}));

test('overlapping refreshes are skipped and destroy cancels its timer and in-flight request', () => browser(async (state, timers) => {
    let calls = 0;
    globalThis.fetch = (_url, { signal }) => new Promise((_resolve, reject) => {
        calls++;
        signal.addEventListener('abort', () => reject(new Error('aborted')));
    });
    state.init();
    const request = state.refresh();
    await state.refresh();
    assert.equal(calls, 1);
    state.destroy();
    await request;
    assert.equal(state.controller.signal.aborted, true);
    assert.deepEqual(timers.at(-1), ['clear', 1]);
    assert.equal(state.refreshing, false);
}));

test('view changes also update current pagination links', () => browser(async state => {
    const link = { href: 'https://dashboard.test/schedules?status=failed&month=2026-10&page=3' };
    state.$refs.results.querySelectorAll = () => [link];
    state.setView('calendar');
    assert.equal(new URL(link.href).searchParams.get('view'), 'calendar');
    assert.equal(new URL(link.href).searchParams.get('status'), 'failed');
}));


test('refresh preserves expanded days without opening other dates or layouts', () => browser(async (state, _timers, results) => {
    const open = { dataset: { calendarMore: 'mobile-2026-10-17' }, open: true };
    const restored = { dataset: { calendarMore: 'mobile-2026-10-17' }, open: false };
    const anotherDay = { dataset: { calendarMore: 'mobile-2026-10-18' }, open: false };
    const desktopDay = { dataset: { calendarMore: 'desktop-2026-10-17' }, open: false };
    results.querySelectorAll = selector => selector === '[data-calendar-more][open]'
        ? [open] : [restored, anotherDay, desktopDay];
    globalThis.fetch = async () => ({ ok: true, text: async () => 'updated' });

    await state.refresh();

    assert.equal(restored.open, true);
    assert.equal(anotherDay.open, false);
    assert.equal(desktopDay.open, false);
}));
