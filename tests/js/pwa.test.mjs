import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { setupPwa } from '../../resources/js/pwa.js';

function browser({ secure = true, standalone = false, buttonPresent = true, registerFails = false } = {}) {
    const listeners = {};
    const clicks = {};
    const calls = [];
    const button = { hidden: true, disabled: false, addEventListener: (name, fn) => { clicks[name] = fn; } };
    const window = {
        isSecureContext: secure,
        matchMedia: () => ({ matches: standalone }),
        addEventListener: (name, fn) => { listeners[name] = fn; },
        console: { warn: (message) => calls.push(message) },
    };
    const navigator = { serviceWorker: { register: (...args) => {
        calls.push(args);
        return registerFails ? Promise.reject(new Error('Unavailable')) : Promise.resolve({});
    } } };
    setupPwa({ window, navigator, document: { querySelector: () => buttonPresent ? button : null } });
    return { listeners, clicks, calls, button };
}

test('secure pages register root worker; insecure pages and unsupported browsers stay usable', async () => {
    assert.deepEqual(browser().calls, [['/sw.js', { scope: '/', updateViaCache: 'none' }]]);
    assert.deepEqual(browser({ secure: false }).calls, []);
    assert.doesNotThrow(() => setupPwa({ window: { isSecureContext: true }, navigator: {}, document: { querySelector: () => null } }));
    const failed = browser({ registerFails: true });
    await Promise.resolve();
    assert.match(failed.calls[1], /No se pudo/);
});

test('install affordance is conditional, prompts once and clears on installation', async () => {
    const page = browser();
    let prompts = 0;
    let prevented = 0;
    const event = { preventDefault() { prevented++; }, async prompt() { prompts++; }, userChoice: Promise.resolve({ outcome: 'dismissed' }) };
    assert.equal(page.button.hidden, true);
    page.listeners.beforeinstallprompt(event);
    assert.equal(prevented, 1);
    assert.equal(page.button.hidden, false);
    await page.clicks.click();
    await page.clicks.click();
    assert.equal(prompts, 1);
    assert.equal(page.button.hidden, true);
    assert.equal(page.button.disabled, false);
    page.listeners.beforeinstallprompt(event);
    page.listeners.appinstalled();
    page.listeners.beforeinstallprompt(event);
    assert.equal(page.button.hidden, true);
    const installed = browser({ standalone: true });
    installed.listeners.beforeinstallprompt(event);
    assert.equal(installed.button.hidden, true);
    assert.deepEqual(browser({ buttonPresent: false }).listeners, {});
});

test('withdrawn install prompt does not leave a disabled control', async () => {
    const page = browser();
    page.listeners.beforeinstallprompt({ preventDefault() {}, prompt() { throw new Error('Expired'); } });
    await page.clicks.click();
    assert.equal(page.button.hidden, true);
    assert.equal(page.button.disabled, false);
});

async function worker() {
    const handlers = {};
    const cached = new Map();
    const deleted = [];
    const calls = [];
    let network = async () => new Response('Private dashboard');
    let claimed = false;
    const cache = {
        async addAll(paths) { for (const path of paths) cached.set(path, new Response('Public offline page')); },
        async match(path) { return cached.get(path)?.clone(); },
    };
    vm.runInNewContext(await readFile(new URL('../../public/sw.js', import.meta.url), 'utf8'), {
        URL, Response,
        self: { location: { origin: 'https://panel.test' }, addEventListener: (name, fn) => { handlers[name] = fn; }, clients: { async claim() { claimed = true; } } },
        caches: { async open() { return cache; }, async keys() { return ['postobon-offline-v0', 'postobon-offline-v1', 'other-app']; }, async delete(name) { deleted.push(name); } },
        fetch: async (...args) => { calls.push(args); return network(...args); },
    });
    async function event(name) {
        let pending;
        handlers[name]({ waitUntil: (promise) => { pending = promise; } });
        await pending;
    }
    function request(path, method = 'GET', mode = 'navigate') {
        let result;
        handlers.fetch({ request: { url: new URL(path, 'https://panel.test').href, method, mode }, respondWith: (promise) => { result = promise; } });
        return result;
    }
    return { event, request, cached, deleted, calls, network: (fn) => { network = fn; }, claimed: () => claimed };
}

test('worker installs only public offline page and removes only its old cache', async () => {
    const sw = await worker();
    await sw.event('install');
    assert.deepEqual([...sw.cached.keys()], ['/offline.html']);
    await sw.event('activate');
    assert.deepEqual(sw.deleted, ['postobon-offline-v0']);
    assert.equal(sw.claimed(), true);
});

test('navigations never cache private pages or replace HTTP errors; network failures use public fallback', async () => {
    const sw = await worker();
    await sw.event('install');
    assert.equal(await (await sw.request('/devices/1')).text(), 'Private dashboard');
    assert.equal(sw.calls[0][1].cache, 'no-store');
    sw.network(async () => new Response('Server error', { status: 500 }));
    assert.equal((await sw.request('/devices/1')).status, 500);
    sw.network(async () => { throw new TypeError('Offline'); });
    assert.equal(await (await sw.request('/dashboard')).text(), 'Public offline page');
    assert.deepEqual([...sw.cached.keys()], ['/offline.html']);
    sw.cached.clear();
    assert.equal((await sw.request('/dashboard')).status, 503);
});

test('worker does not intercept APIs, media, cross-origin requests, subresources or mutations', async () => {
    const sw = await worker();
    for (const path of ['/api/devices', '/media/1/content', '/media', '/storage/video.mp4', '/build/assets/app.js', 'https://external.test/dashboard']) {
        assert.equal(sw.request(path), undefined);
    }
    assert.equal(sw.request('/devices/1', 'POST'), undefined);
    assert.equal(sw.request('/device-previews', 'GET', 'cors'), undefined);
    assert.equal(sw.request('/devices/1', 'GET', 'same-origin'), undefined);
    assert.equal(sw.calls.length, 0);
});
