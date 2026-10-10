import test from 'node:test';
import assert from 'node:assert/strict';
import { createLibraryPlayback } from '../../resources/js/library-playback.js';

const response = (payload, status = 200) => ({ ok: status < 400, status, json: async () => payload });
const event = { currentTarget: { action: '/instant-play/media' } };

function fixture(fetch) {
    const timers = new Map();
    const values = new Map();
    const form = { media_id: 58702, targets: ['z2:AABB'] };
    let nextTimer = 0;
    const state = createLibraryPlayback('/fleet/uploads/__UPLOAD_ID__', {
        fetch, formData: () => form,
        setInterval: (callback, delay) => { assert.equal(delay, 2500); timers.set(++nextTimer, callback); return nextTimer; },
        clearInterval: (id) => timers.delete(id),
        storage: { setItem: (key, value) => values.set(key, value), getItem: (key) => values.get(key), removeItem: (key) => values.delete(key) },
    });
    return { state, timers, values, form };
}

test('uses selected library identity, tracks progress and stops after partial completion', async () => {
    const calls = [];
    const replies = [response({ upload_id: 'job-a' }, 202), response({ status: 'processing', progress: 40 }),
        response({ status: 'completed', progress: 100, result: { succeeded: 1, failed: 1 } })];
    const { state, timers, values, form } = fixture(async (url, options) => { calls.push([url, options]); return replies.shift(); });
    await state.submit(event);
    assert.equal(calls[0][0], '/instant-play/media');
    assert.equal(calls[0][1].body, form);
    assert.equal(calls[0][1].credentials, 'same-origin');
    assert.equal(calls[1][0], '/fleet/uploads/job-a');
    assert.equal(state.busy, true);
    assert.equal(state.progress, 40);
    assert.equal(values.get('instant-play-upload-id'), 'job-a');
    await [...timers.values()][0]();
    assert.equal(state.busy, false);
    assert.equal(state.message, 'Órdenes enviadas: 1. Fallidas: 1.');
    assert.equal(timers.size, 0);
    assert.equal(values.size, 0);
});

test('rejected dispatch reports error without provider exception details or polling', async () => {
    const { state, timers } = fixture(async () => response({ message: 'http://secret.test?token=private' }, 500));
    await state.submit(event);
    assert.equal(state.status, 'failed');
    assert.doesNotMatch(state.message, /secret|token|private/);
    assert.equal(timers.size, 0);
});

test('transient status failure keeps the operation pending and can recover', async () => {
    const replies = [response({ upload_id: 'job-b' }, 202), response({}, 503), response({ status: 'completed', result: { succeeded: 1, failed: 0 } })];
    const { state, timers } = fixture(async () => replies.shift());
    await state.submit(event);
    assert.equal(state.busy, true);
    assert.match(state.message, /puede continuar/);
    await [...timers.values()][0]();
    assert.equal(state.status, 'completed');
    assert.equal(timers.size, 0);
});

test('missing or unauthorized status stops polling without claiming playback', async () => {
    for (const status of [403, 404]) {
        const replies = [response({ upload_id: 'job-c' }, 202), response({}, status)];
        const { state, timers } = fixture(async () => replies.shift());
        await state.submit(event);
        assert.equal(state.status, 'failed');
        assert.match(state.message, /No se pudo recuperar/);
        assert.equal(timers.size, 0);
    }
});

test('expired session retains accepted operation and blocks duplicate dispatch until consultation', async () => {
    let requests = 0;
    const replies = [response({ upload_id: 'job-login' }, 202), response({}, 401)];
    const { state, timers, values } = fixture(async () => { requests++; return replies.shift(); });
    await state.submit(event);
    assert.equal(state.id, 'job-login');
    assert.equal(state.status, 'session_expired');
    assert.equal(state.busy, true);
    assert.equal(timers.size, 0);
    assert.equal(values.get('instant-play-upload-id'), 'job-login');
    assert.match(state.message, /consulta esta operación/);
    await state.submit(event);
    assert.equal(requests, 2);
});

test('avoids duplicate submit and overlapping polls; destroy prevents late state updates', async () => {
    let release;
    let requests = 0;
    const { state, timers } = fixture(async () => {
        requests++;
        if (requests === 1) return response({ upload_id: 'job-d' }, 202);
        return new Promise((resolve) => { release = resolve; });
    });
    const submitting = state.submit(event);
    await new Promise((resolve) => setImmediate(resolve));
    await state.submit(event);
    await state.poll();
    assert.equal(requests, 2);
    assert.equal(timers.size, 1);
    state.destroy();
    release(response({ status: 'completed', progress: 100, result: { succeeded: 1 } }));
    await submitting;
    assert.equal(timers.size, 0);
    assert.equal(state.status, 'queued');
});

test('blocked recovery storage never interrupts accepted playback or completion', async () => {
    const replies = [response({ upload_id: 'job-storage' }, 202), response({ status: 'processing', progress: 30 }),
        response({ status: 'completed', progress: 100, result: { succeeded: 1, failed: 0 } })];
    let tick;
    const state = createLibraryPlayback('/fleet/uploads/__UPLOAD_ID__', {
        fetch: async () => replies.shift(), formData: () => ({}),
        storage: { setItem() { throw new Error('quota exceeded'); }, getItem() { throw new Error('blocked'); } },
        setInterval: (callback) => { tick = callback; return 1; }, clearInterval: () => {},
    });
    await state.submit(event);
    assert.equal(state.status, 'processing');
    assert.equal(state.busy, true);
    await tick();
    assert.equal(state.status, 'completed');
    assert.equal(state.busy, false);
});
