import test from 'node:test';
import assert from 'node:assert/strict';
import { createDeviceLive } from '../../resources/js/device-live.js';
import { createDeviceList } from '../../resources/js/device-list.js';

const row = { key: 'wl35:a', type: 'wl35', searchable: 'Entrada', establishmentId: '1',
    status: 'online', online: true, connected: true, powered: true, bluetooth: true, sessionReady: true, uploading: false };
const snapshot = operating => ({ failed: false, devices: [{ key: row.key, operating, current_video: 2 }] });

test('reported power and Bluetooth refresh controls and counters without resetting filters or selection', () => {
    const state = createDeviceList([{ ...row }]);
    state.search = 'Entrada'; state.selectedFleetKeys = [row.key]; state.actionKey = row.key;
    state.applySnapshot(snapshot({ ...row, status: 'powered_off', powered: false, bluetooth: false }));
    assert.equal(state.rows[0].powered, false);
    assert.equal(state.rows[0].bluetooth, false);
    assert.equal(state.rows[0].content, 2);
    assert.equal(state.countStatus('online'), 0);
    assert.equal(state.countStatus('powered_off'), 1);
    assert.equal(state.canControl(state.rows[0]), true);
    assert.deepEqual(state.selectedFleetKeys, [row.key]);
    assert.equal(state.search, 'Entrada'); assert.equal(state.actionKey, row.key);
    state.applySnapshot(snapshot({ ...row, status: 'online' }));
    assert.equal(state.countStatus('online'), 1);
});

test('missing, unavailable, disconnected and busy WL35 states disable controls honestly', () => {
    const state = createDeviceLive([{ ...row }]);
    state.applySnapshot({ devices: [], failed: true });
    assert.equal(state.stateFailed, true);
    assert.equal(state.rows[0].status, 'unavailable'); assert.equal(state.rows[0].powered, null);
    assert.equal(state.canControl(state.rows[0]), false);
    for (const unavailable of [{ connected: false }, { online: false }, { sessionReady: false }, { uploading: true }]) {
        assert.equal(state.canControl({ ...row, ...unavailable }), false);
    }
    assert.equal(state.canControl({ ...row, bluetooth: null }, 'bluetooth'), false);
});

test('accepted commands request telemetry without optimistically changing device state or duplicating requests', async () => {
    let complete, requests = 0; const events = [];
    const state = createDeviceLive([{ ...row }], { formData: (_, submitter) => submitter,
        fetch: async (_, options) => { requests++; assert.equal(options.body.value, '0'); return new Promise(resolve => complete = resolve); } });
    state.$dispatch = name => events.push(name);
    const pending = state.sendDeviceCommand({ action: '/fleet/command' }, { value: '0' });
    assert.equal(state.commandBusy, true);
    await state.sendDeviceCommand({ action: '/fleet/command' });
    assert.equal(requests, 1);
    complete({ ok: true, json: async () => ({ success: true, message: 'Orden enviada' }) }); await pending;
    assert.equal(state.rows[0].powered, true); assert.equal(state.commandBusy, false);
    assert.equal(state.commandFailed, false); assert.deepEqual(events, ['refresh-devices']);
});

test('partial failures, validation errors and non-JSON failures keep reported state and release controls', async () => {
    for (const response of [
        { ok: false, json: async () => ({ success: false, message: 'Un equipo falló' }) },
        { ok: false, json: async () => ({ errors: { targets: ['Selecciona un equipo'] } }) },
        { ok: true, json: async () => { throw new Error('Sesión vencida'); } },
    ]) {
        const state = createDeviceLive([{ ...row }], { formData: () => ({}), fetch: async () => response });
        await state.sendDeviceCommand({ action: '/command' });
        assert.equal(state.commandFailed, true); assert.equal(state.commandBusy, false);
        assert.equal(state.rows[0].powered, true); assert.ok(state.commandMessage);
    }
});

test('an unconfirmed command timeout releases controls without retrying or changing power', async () => {
    let deadline, calls = 0;
    const state = createDeviceLive([{ ...row }], {
        formData: () => ({}), setTimeout: callback => { deadline = callback; return 1; }, clearTimeout: () => {},
        fetch: async (_, { signal }) => {
            calls++;
            return new Promise((_, reject) => signal.addEventListener('abort', () => reject(new DOMException('Timeout', 'AbortError'))));
        },
    });
    const pending = state.sendDeviceCommand({ action: '/command' }); deadline(); await pending;
    assert.equal(calls, 1); assert.equal(state.commandBusy, false); assert.equal(state.commandFailed, true);
    assert.match(state.commandMessage, /No se pudo confirmar/); assert.equal(state.rows[0].powered, true);
});
