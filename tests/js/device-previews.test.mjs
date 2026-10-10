import test from 'node:test';
import assert from 'node:assert/strict';
import { createDevicePreviews, createPreviewPlayer } from '../../resources/js/device-previews.js';

function player() {
    let source = null;
    const video = {
        loads: 0, pauses: 0, plays: 0, paused: true, ended: false,
        getAttribute: () => source,
        setAttribute: (_, value) => { source = value; },
        removeAttribute: () => { source = null; },
        load() { this.loads++; },
        pause() { this.pauses++; this.paused = true; },
        play() { this.plays++; this.paused = false; return Promise.resolve(); },
    };
    return Object.assign(createPreviewPlayer(), { $refs: { video }, visible: true });
}

const ready = (url = '/media/1/content') => ({ key: 'wl35:fan-a', status: 'ready', url });

test('polling the same video preserves playback position and user pause', () => {
    const preview = player();
    preview.update(ready());
    preview.$refs.video.pause();
    const calls = { loads: preview.$refs.video.loads, plays: preview.$refs.video.plays };
    preview.update({ ...ready(), name: 'Nuevo nombre', last_seen: 'nuevo reporte' });
    assert.equal(preview.$refs.video.loads, calls.loads);
    assert.equal(preview.$refs.video.plays, calls.plays);
});

test('new content changes source, offline removes it, and reconnection resumes it', () => {
    const preview = player();
    preview.update(ready());
    preview.update(ready('/media/2/content'));
    assert.equal(preview.$refs.video.getAttribute('src'), '/media/2/content');
    assert.equal(preview.$refs.video.loads, 2);
    preview.update({ ...ready(), status: 'offline' });
    assert.equal(preview.$refs.video.getAttribute('src'), null);
    assert.equal(preview.source, null);
    preview.update(ready());
    assert.equal(preview.$refs.video.getAttribute('src'), '/media/1/content');
});

test('file errors survive polls of the same source, retry reloads, new content clears error', () => {
    const preview = player();
    preview.device = ready();
    preview.update(preview.device);
    preview.failed();
    preview.update(ready());
    assert.equal(preview.error, true);
    preview.retry();
    assert.equal(preview.error, false);
    assert.equal(preview.$refs.video.loads, 3);
    preview.failed();
    preview.update(ready('/media/2/content'));
    assert.equal(preview.error, false);
});

test('device sources are deferred offscreen and only playing videos resume after visibility changes', () => {
    const preview = player();
    preview.inViewport = false;
    preview.update(ready());
    assert.equal(preview.$refs.video.getAttribute('src'), null);
    assert.equal(preview.$refs.video.plays, 0);
    preview.inViewport = true;
    preview.syncPlayback();
    assert.equal(preview.$refs.video.plays, 1);
    preview.inViewport = false;
    preview.syncPlayback();
    preview.visible = false;
    preview.syncPlayback();
    preview.inViewport = true;
    preview.syncPlayback();
    assert.equal(preview.$refs.video.paused, true);
    preview.visible = true;
    preview.syncPlayback();
    assert.equal(preview.$refs.video.plays, 2);
    assert.equal(preview.$refs.video.loads, 1);
    preview.$refs.video.pause();
    preview.inViewport = false;
    preview.syncPlayback();
    preview.inViewport = true;
    preview.syncPlayback();
    assert.equal(preview.$refs.video.plays, 2);
});

test('offscreen content changes discard old video, retry waits, and destroy prevents queued initialization', () => {
    const preview = player();
    preview.update(ready());
    preview.inViewport = false;
    preview.syncPlayback();
    preview.device = ready('/media/2/content');
    preview.update(preview.device);
    assert.equal(preview.$refs.video.getAttribute('src'), null);
    preview.failed();
    preview.retry();
    assert.equal(preview.$refs.video.getAttribute('src'), null);
    preview.inViewport = true;
    preview.syncPlayback();
    assert.equal(preview.$refs.video.getAttribute('src'), '/media/2/content');
    const ticks = [];
    preview.$nextTick = callback => ticks.push(callback);
    preview.init();
    preview.destroy();
    ticks.shift()();
    preview.update(ready());
    assert.equal(preview.$refs.video.getAttribute('src'), null);
});

function polling(fetch) {
    const listeners = new Map();
    const doc = {
        hidden: false,
        addEventListener: (name, listener) => listeners.set(name, listener),
        removeEventListener: name => listeners.delete(name),
    };
    let scheduled, cancelled;
    const state = createDevicePreviews('/device-previews', 'wl35:fan-a', {
        fetch, document: doc,
        setInterval(callback, delay) { scheduled = callback; assert.equal(delay, 10000); return 7; },
        clearInterval(id) { cancelled = id; },
    });
    return { state, doc, listeners, tick: () => scheduled(), cancelled: () => cancelled };
}

test('one polling loop filters a detail, skips hidden tabs, and cleans up on removal', async () => {
    let requests = 0;
    const fixture = polling(async () => {
        requests++;
        return { ok: true, json: async () => ({ devices: [ready(), { ...ready(), key: 'z2:other' }] }) };
    });
    fixture.state.init();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests, 1);
    assert.equal(fixture.state.devices.length, 1);
    fixture.doc.hidden = true;
    await fixture.listeners.get('visibilitychange')();
    await fixture.tick();
    assert.equal(requests, 1);
    assert.equal(fixture.state.visible, false);
    fixture.doc.hidden = false;
    await fixture.listeners.get('visibilitychange')();
    assert.equal(requests, 2);
    assert.equal(fixture.state.visible, true);
    fixture.state.destroy();
    assert.equal(fixture.cancelled(), 7);
    assert.equal(fixture.listeners.size, 0);
    await fixture.state.refresh();
    assert.equal(requests, 2);
});

test('failed status refresh clears active URLs and does not overlap pending requests', async () => {
    let reject;
    let requests = 0;
    const fixture = polling(() => {
        requests++;
        return new Promise((_, rejectRequest) => { reject = rejectRequest; });
    });
    fixture.state.devices = [ready()];
    const request = fixture.state.refresh();
    await fixture.state.refresh();
    assert.equal(requests, 1);
    reject(new Error('unavailable'));
    await request;
    assert.equal(fixture.state.failed, true);
    assert.equal(fixture.state.devices[0].status, 'unavailable');
    assert.equal(fixture.state.devices[0].url, null);
});
