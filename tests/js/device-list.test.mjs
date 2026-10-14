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
