import test from 'node:test';
import assert from 'node:assert/strict';
import { createMediaDurationStore, createMediaPreview, formatDuration } from '../../resources/js/media-preview.js';

function player(known = 0) {
    const video = {
        duration: 3, readyState: 2, currentTime: 0,
        pauses: 0, loads: 0,
        pause() { this.pauses++; },
        load() { this.loads++; },
        removeAttribute(name) { this.removed = name; },
    };
    const ticks = [];
    const state = createMediaPreview('42', '/media/42/content', 'video', known);
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

test('shared duration appears in both library views without replacing known duration', () => {
    const store = createMediaDurationStore();
    store.discover('42', 10);
    store.discover('42', Infinity);
    assert.equal(store.label('42'), '00:10');
    assert.equal(store.label('42', 20), '00:20');
});
