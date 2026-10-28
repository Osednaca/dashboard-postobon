import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const view = readFileSync(new URL('../../resources/views/instant-play/index.blade.php', import.meta.url), 'utf8');
const script = view.match(/<script>\s*([\s\S]*?)\s*<\/script>/)[1]
    .replace(/playbackStatusTemplate: {{[^\n]+}},/, "playbackStatusTemplate: '/status/__UPLOAD_ID__',")
    .replace(/devices: {{ Js::from\([\s\S]*?\)->values\(\)\) }},/, 'devices: [],');

function setup({ mobile = true, reducedMotion = false, fetch } = {}) {
    const scrolls = [];
    const ticks = [];
    const window = {
        matchMedia: query => ({ matches: query.includes('max-width') ? mobile : reducedMotion }),
        localStorage: { setItem() {}, removeItem() {} },
    };
    const instantPlay = runInNewContext(`${script}; instantPlay`, { window, fetch, FormData: class {} });
    const state = instantPlay();
    state.$refs = {
        playbackForm: { scrollIntoView: options => scrolls.push(['form', options.behavior, options.block]) },
        playbackStatus: { scrollIntoView: options => scrolls.push(['status', options.behavior, options.block]) },
    };
    state.$nextTick = callback => ticks.push(callback);
    state.startPlaybackClock = () => {};
    state.stopPlaybackPolling = () => {};
    state.startPlaybackPolling = () => {};
    return { state, scrolls, flush: () => ticks.splice(0).forEach(callback => callback()) };
}

const submit = { preventDefault() {}, currentTarget: { action: '/play', querySelector: () => null } };

test('individual selection scrolls to the form only on mobile, after rendering', () => {
    assert.match(view, /x-ref="playbackForm"/);
    assert.match(view, /x-ref="playbackStatus"/);
    const mobile = setup();
    mobile.state.selectDevice('z2:one');
    assert.equal(mobile.state.selectedDevice, 'z2:one');
    assert.deepEqual(mobile.scrolls, []);
    mobile.flush();
    assert.deepEqual(mobile.scrolls, [['form', 'smooth', 'start']]);
    const desktop = setup({ mobile: false });
    desktop.state.selectDevice('z2:two');
    desktop.flush();
    assert.equal(desktop.state.selectedDevice, 'z2:two');
    assert.deepEqual(desktop.scrolls, []);
});

test('campaign and bulk selections keep their existing behavior without scrolling', () => {
    const { state, flush, scrolls } = setup();
    state.mode = 'campaign';
    state.selectDevice('wl35:one');
    assert.equal(state.selectedDevice, 'wl35:one');
    state.mode = 'bulk';
    state.selectDevice('wl35:two');
    assert.equal(state.selectedDevice, 'wl35:one');
    flush();
    assert.deepEqual(scrolls, []);
});

test('play scrolls to the status for both accepted and failed requests and respects reduced motion', async () => {
    for (const ok of [true, false]) {
        const { state, scrolls, flush } = setup({ reducedMotion: true, fetch: async () => ({
            ok, status: ok ? 200 : 500, json: async () => ok ? { upload_id: 'one' } : { message: 'Error' },
        }) });
        await state.submitPlayback(submit);
        flush();
        assert.deepEqual(scrolls, [['status', 'auto', 'start']]);
        assert.equal(state.playback.status, ok ? 'queued' : 'failed');
        if (!ok) assert.equal(state.playback.error, 'Error');
    }
});
