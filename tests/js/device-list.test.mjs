import test from 'node:test';
import assert from 'node:assert/strict';
import { createDeviceList } from '../../resources/js/device-list.js';

const rows = [
    { key: 'z2:AABB', type: 'z2', searchable: 'Recepción AA:BB Parque', establishmentId: '2', status: 'online' },
    { key: 'wl35:one', type: 'wl35', searchable: 'Entrada one Centro', establishmentId: '3', status: 'powered_off' },
    { key: 'wl35:two', type: 'wl35', searchable: 'Bodega two Parque', establishmentId: '2', status: 'offline' },
];

test('filters intersect and counters describe only matching rows; reset restores the page', () => {
    const state = { ...createDeviceList(rows) };
    assert.equal(state.visibleRows().length, 3);
    assert.equal(state.countStatus('powered_off'), 1);
    state.search = '  PARQUE  ';
    assert.deepEqual(state.visibleRows().map(row => row.key), ['z2:AABB', 'wl35:two']);
    state.protocolFilter = 'wl35';
    state.establishmentFilter = '2';
    state.statusFilter = 'online';
    assert.equal(state.visibleRows().length, 0);
    state.statusFilter = 'offline';
    assert.deepEqual(state.visibleRows().map(row => row.key), ['wl35:two']);
    state.resetFilters();
    assert.equal(state.visibleRows().length, 3);
});

test('single-device playback snapshots its targets without changing the bulk selection', () => {
    const state = { ...createDeviceList(rows) };
    state.selectedFleetKeys = ['z2:AABB', 'wl35:two'];
    state.selectedIds = ['7'];
    state.openPlayback(['wl35:one'], 'Entrada');
    assert.deepEqual(state.playbackTargets, ['wl35:one']);
    assert.deepEqual(state.selectedFleetKeys, ['z2:AABB', 'wl35:two']);
    assert.deepEqual(state.selectedIds, ['7']);
    assert.equal(state.playbackName, 'Entrada');
    assert.equal(state.showFleetPlayModal, true);
    state.openPlayback();
    state.selectedFleetKeys.pop();
    assert.deepEqual(state.playbackTargets, ['z2:AABB', 'wl35:two']);
});

test('closing actions restores and clears focus; opening a dialog retains its origin', () => {
    const state = { ...createDeviceList(rows) };
    let focusCount = 0;
    const trigger = { focus: () => focusCount++ };
    state.actionTrigger = trigger;
    state.actionKey = 'wl35:one';
    state.closeActions(false);
    assert.equal(state.actionTrigger, trigger);
    assert.equal(focusCount, 0);
    state.closeActions();
    assert.equal(focusCount, 1);
    assert.equal(state.actionTrigger, null);
});

test('initial focus waits for the visible frame after Alpine applies dialog state', () => {
    const state = { ...createDeviceList(rows) };
    const tasks = [];
    let focused = false, visible = false;
    const originalWindow = globalThis.window;
    globalThis.window = { requestAnimationFrame: callback => tasks.push(callback) };
    state.$nextTick = callback => tasks.push(callback);
    try {
        state.focusAfterRender(() => ({ getClientRects: () => visible ? [{}] : [], focus: () => focused = true }));
        tasks.shift()();
        assert.equal(focused, false);
        visible = true;
        tasks.shift()();
        assert.equal(focused, true);
    } finally {
        globalThis.window = originalWindow;
    }
});

test('keyboard cycles dialog focus and Escape dismisses without submitting commands', () => {
    const state = { ...createDeviceList(rows) };
    let focused = '', prevented = 0;
    const first = { offsetParent: {}, focus: () => focused = 'first' };
    const last = { offsetParent: {}, focus: () => focused = 'last' };
    const dialog = { querySelectorAll: () => [first, last] };
    first.closest = last.closest = () => dialog;
    state.dialogKeydown({ key: 'Tab', target: last, shiftKey: false, preventDefault: () => prevented++ });
    assert.equal(focused, 'first');
    state.dialogKeydown({ key: 'Tab', target: first, shiftKey: true, preventDefault: () => prevented++ });
    assert.equal(focused, 'last');
    state.showFleetPlayModal = true;
    state.dialogKeydown({ key: 'Escape', target: first, preventDefault: () => prevented++ });
    assert.equal(state.showFleetPlayModal, false);
    assert.equal(prevented, 3);
});

const removalRows = [
    { ...rows[0], removalKey: 'z2:7', name: 'Recepción', identifier: 'AA:BB' },
    { ...rows[1], removalKey: 'wl35:one', name: 'Entrada', identifier: 'one' },
    { ...rows[2], removalKey: 'wl35:two', name: 'Bodega', identifier: 'two' },
];
const removalForm = { action: '/devices/bulk-remove-video', querySelector: () => ({ value: 'csrf' }) };

test('removal excludes stale hidden selections and snapshots local identities and names', () => {
    const state = createDeviceList(removalRows);
    state.selectedFleetKeys = ['z2:AABB', 'wl35:one', 'wl35:two', 'wl35:missing'];
    state.establishmentFilter = '2';
    state.openVideoRemoval();
    assert.deepEqual(state.removalTargets.map(target => target.key), ['z2:7', 'wl35:two']);
    state.selectedFleetKeys = [];
    state.rows[0] = { ...state.rows[0], name: 'Updated elsewhere' };
    assert.equal(state.removalTargets[0].name, 'Recepción');
    state.closeVideoRemoval();
    state.openVideoRemoval(['wl35:one']);
    assert.equal(state.showVideoRemovalModal, false);
    state.resetFilters();
    state.openVideoRemoval(['wl35:one']);
    assert.deepEqual(state.removalTargets.map(target => target.key), ['wl35:one']);
});

test('removal posts a stable visible snapshot and active group once, and retains partial results', async () => {
    const calls = [];
    let resolveResponse;
    const state = createDeviceList(removalRows, { fetch: async (...args) => {
        calls.push(args);
        return await new Promise(resolve => resolveResponse = resolve);
    } });
    state.selectedFleetKeys = ['z2:AABB', 'wl35:one', 'wl35:two'];
    state.establishmentFilter = '2';
    state.activeGroupId = '12';
    state.openVideoRemoval();
    state.removalMediaId = '9';
    const pending = state.submitVideoRemoval(removalForm);
    state.closeVideoRemoval();
    assert.equal(state.showVideoRemovalModal, true);
    await state.submitVideoRemoval(removalForm);
    assert.equal(calls.length, 1);
    assert.deepEqual(JSON.parse(calls[0][1].body), { media_id: '9', device_keys: ['z2:7', 'wl35:two'], group_id: '12' });
    assert.equal(calls[0][1].headers['X-CSRF-TOKEN'], 'csrf');
    const results = [{ key: 'z2:7', name: 'Recepción', status: 'accepted', message: 'Pendiente' },
        { key: 'wl35:two', name: 'Bodega', status: 'failed', message: 'Sin asociación' }];
    resolveResponse({ ok: true, json: async () => ({ results }) });
    await pending;
    assert.deepEqual(state.removalResults, results);
    assert.equal(state.removalBusy, false);
    await state.submitVideoRemoval(removalForm);
    assert.equal(calls.length, 1);
    state.closeVideoRemoval();
    assert.equal(state.showVideoRemovalModal, false);
});

test('validation errors allow correction, uncertain network failures do not silently retry', async () => {
    let calls = 0;
    const state = createDeviceList(removalRows, { fetch: async () => {
        calls++;
        if (calls === 1) return { ok: false, status: 422, json: async () => ({ errors: { device_keys: ['El grupo cambió.'] } }) };
        throw new TypeError('Network failed');
    } });
    state.openVideoRemoval(['z2:AABB']);
    state.removalMediaId = '9';
    await state.submitVideoRemoval(removalForm);
    assert.equal(state.removalError, 'El grupo cambió.');
    assert.equal(state.removalAttempted, false);
    await state.submitVideoRemoval(removalForm);
    assert.match(state.removalError, /Revisa los equipos/);
    assert.equal(state.removalAttempted, true);
    await state.submitVideoRemoval(removalForm);
    assert.equal(calls, 2);
});
