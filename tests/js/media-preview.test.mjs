import test from 'node:test';
import assert from 'node:assert/strict';
import { createMediaDurationStore, createMediaPreview, formatDuration } from '../../resources/js/media-preview.js';

function player(known = 0, compressed = false, dependencies = {}) {
    const video = {
        duration: 3, readyState: 2, currentTime: 0,
        pauses: 0, loads: 0, plays: 0, paused: true, ended: false,
        pause() { this.pauses++; this.paused = true; },
        play() { this.plays++; this.paused = false; return Promise.resolve(); },
        load() { this.loads++; },
        removeAttribute(name) { this.removed = name; },
    };
    const ticks = [];
    const state = createMediaPreview('42', '/media/42/content', 'video', known, compressed, dependencies);
    state.$refs = { video };
    state.$store = { mediaDurations: createMediaDurationStore() };
    state.$nextTick = (callback) => ticks.push(callback);
    return { state, video, ticks };
}

test('formats video duration without wrapping hours and ignores invalid metadata', () => {
    assert.equal(formatDuration(3661), '1:01:01');
    assert.equal(formatDuration(90061), '25:01:01');
    assert.equal(formatDuration(3.9), '00:03');
    for (const invalid of [undefined, null, Infinity, NaN, -1, 0]) assert.equal(formatDuration(invalid), '—');
});

test('extracts duration and seeks a paused frame before marking preview ready', () => {
    const { state, video } = player();
    state.start();
    state.metadata(video);
    assert.equal(state.$store.mediaDurations.label('42'), '00:03');
    assert.equal(video.currentTime, 0.1);
    state.loaded();
    assert.equal(state.status, 'loading');
    state.ready();
    assert.equal(state.status, 'ready');
    assert.equal(video.pauses, 0);
});

test('keeps known duration through load error and retry waits for source binding', () => {
    const { state, video, ticks } = player(3661);
    state.start();
    state.metadata(video);
    state.fail();
    state.ready();
    assert.equal(state.status, 'error');
    assert.equal(state.$store.mediaDurations.label('42', 3661), '1:01:01');
    state.retry();
    assert.equal(state.source, null);
    ticks.shift()();
    assert.equal(state.source, '/media/42/content');
    assert.equal(video.loads, 0);
    ticks.shift()();
    assert.equal(video.loads, 1);
    state.ready();
    assert.equal(state.status, 'ready');
});

test('short or unseekable videos use an available first frame without losing metadata', () => {
    const { state, video } = player();
    video.duration = 0.1;
    state.metadata(video);
    assert.equal(video.currentTime, 0.05);
    Object.defineProperty(video, 'currentTime', { set() { throw new Error('Not seekable'); } });
    state.metadata(video);
    assert.equal(state.status, 'ready');
    assert.equal(state.$store.mediaDurations.values['42'], 0.1);
});

test('loads only visible media and destroys observer, resource and queued retries', () => {
    let observer;
    const previous = globalThis.IntersectionObserver;
    globalThis.IntersectionObserver = class {
        constructor(callback) { this.callback = callback; observer = this; }
        observe() {}
        disconnect() { this.disconnected = true; }
    };
    try {
        const { state, video, ticks } = player();
        state.$el = {};
        state.init();
        observer.callback([{ isIntersecting: false }]);
        assert.equal(state.source, null);
        observer.callback([{ isIntersecting: true }]);
        assert.equal(state.source, '/media/42/content');
        assert.notEqual(observer.disconnected, true);
        state.retry();
        state.destroy();
        ticks.shift()();
        assert.equal(state.source, null);
        assert.equal(observer.disconnected, true);
        assert.equal(video.removed, 'src');
    } finally {
        globalThis.IntersectionObserver = previous;
    }
});

test('media pauses offscreen and in hidden tabs, resumes previous playback and preserves manual pause', () => {
    const listeners = new Map();
    let observer;
    const previousObserver = globalThis.IntersectionObserver;
    const previousDocument = globalThis.document;
    const doc = globalThis.document = {
        hidden: true,
        addEventListener: (name, callback) => listeners.set(name, callback),
        removeEventListener: name => listeners.delete(name),
    };
    globalThis.IntersectionObserver = class {
        constructor(callback) { this.callback = callback; observer = this; }
        observe() {}
        disconnect() { this.disconnected = true; }
    };
    try {
        const { state, video, ticks } = player();
        state.init();
        observer.callback([{ isIntersecting: true }]);
        assert.equal(state.source, null);
        doc.hidden = false;
        listeners.get('visibilitychange')();
        assert.equal(state.source, '/media/42/content');
        assert.equal(video.plays, 0);
        video.play();
        observer.callback([{ isIntersecting: false }]);
        assert.equal(video.paused, true);
        doc.hidden = true;
        listeners.get('visibilitychange')();
        observer.callback([{ isIntersecting: true }]);
        assert.equal(video.plays, 1);
        doc.hidden = false;
        listeners.get('visibilitychange')();
        assert.equal(video.plays, 2);
        video.pause();
        observer.callback([{ isIntersecting: false }]);
        observer.callback([{ isIntersecting: true }]);
        assert.equal(video.plays, 2);
        observer.callback([{ isIntersecting: false }]);
        state.fail();
        state.retry();
        ticks.shift()();
        ticks.shift()();
        assert.equal(state.source, null);
        observer.callback([{ isIntersecting: true }]);
        assert.equal(state.source, '/media/42/content');
        state.destroy();
        assert.equal(listeners.size, 0);
        assert.equal(observer.disconnected, true);
        const plays = video.plays;
        observer.callback([{ isIntersecting: true }]);
        assert.equal(video.plays, plays);
    } finally {
        globalThis.IntersectionObserver = previousObserver;
        globalThis.document = previousDocument;
    }
});

test('shared duration appears in both library views without replacing known duration', () => {
    const store = createMediaDurationStore();
    store.discover('42', 10);
    store.discover('42', Infinity);
    assert.equal(store.label('42'), '00:10');
    assert.equal(store.label('42', 20), '00:20');
});

function compressedPlayer(responses) {
    const timers = new Map();
    const calls = [];
    let timerId = 0;
    const result = player(28, true, {
        fetch: async (url, options) => {
            calls.push({ url, options });
            const response = responses.shift();
            return { ok: true, json: async () => response };
        },
        setTimeout: (callback, delay) => {
            timers.set(++timerId, { callback, delay });
            return timerId;
        },
        clearTimeout: id => timers.delete(id),
    });
    return { ...result, calls, timers };
}

test('campaign waits for compressed media, deduplicates polling and never loads original while pending', async () => {
    const { state, video, calls, timers } = compressedPlayer([
        { status: 'pending', url: null, message: 'Preparando…' },
        { status: 'ready', url: '/media/42/content?preview=compressed' },
    ]);
    const first = state.start();
    state.start();
    assert.equal(state.source, null);
    await first;
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/media/42/content?preview_status=1');
    assert.equal(calls[0].options.credentials, 'same-origin');
    assert.equal(state.status, 'pending');
    assert.equal(state.source, null);
    const [id, timer] = [...timers][0];
    assert.equal(timer.delay, 5000);
    timers.delete(id);
    timer.callback();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(state.source, '/media/42/content?preview=compressed');
    assert.equal(state.message, null);
    assert.equal(timers.size, 0);
    state.metadata(video);
    state.loaded();
    assert.equal(state.status, 'pending');
    state.ready();
    assert.equal(state.status, 'ready');
    state.destroy();
});

test('campaign polling pauses offscreen, resumes when visible, and stops on destruction', async () => {
    const { state, calls, timers } = compressedPlayer(Array(3).fill({ status: 'pending', url: null }));
    await state.start();
    state.inViewport = false;
    state.syncPlayback();
    assert.equal(timers.size, 0);
    await state.start();
    assert.equal(calls.length, 1);
    state.inViewport = true;
    await state.start();
    assert.equal(calls.length, 2);
    state.destroy();
    assert.equal(timers.size, 0);
    assert.equal(state.source, null);
});

test('failed or unavailable compression stays explicit, does not fall back and supports retry', async () => {
    for (const status of ['error', 'unavailable']) {
        const { state, ticks, timers } = compressedPlayer([
            { status, url: null, message: 'Vista previa no disponible.' },
            { status: 'ready', url: '/media/42/content?preview=ready' },
        ]);
        await state.start();
        assert.equal(state.source, null);
        assert.equal(state.status, status);
        assert.equal(state.message, 'Vista previa no disponible.');
        assert.equal(timers.size, 0);
        state.retry();
        ticks.shift()();
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(state.source, '/media/42/content?preview=ready');
        state.destroy();
    }
});

test('campaign preparation has a bounded wait and aborts outstanding requests on destruction', async () => {
    const { state, timers, calls } = compressedPlayer(Array(72).fill({ status: 'pending', url: null }));
    await state.start();
    for (let i = 0; i < 72; i++) {
        const [id, timer] = [...timers][0];
        timers.delete(id);
        timer.callback();
        await new Promise(resolve => setImmediate(resolve));
    }
    assert.equal(calls.length, 72);
    assert.equal(state.status, 'error');
    assert.equal(state.source, null);
    assert.equal(timers.size, 0);
    let finish;
    let signal;
    const pending = player(0, true, { fetch: async (_url, options) => {
        signal = options.signal;
        return new Promise(resolve => { finish = resolve; });
    } });
    const request = pending.state.start();
    pending.state.destroy();
    assert.equal(signal.aborted, true);
    finish({ ok: true, json: async () => ({ status: 'ready', url: '/must-not-load' }) });
    await request;
    assert.equal(pending.state.source, null);
});

test('campaign status network failures expose retry without fetching the original', async () => {
    const { state } = player(0, true, { fetch: async () => { throw new Error('offline'); } });
    await state.start();
    assert.equal(state.status, 'error');
    assert.equal(state.source, null);
    assert.match(state.message, /No se pudo consultar/);
    state.destroy();
});
