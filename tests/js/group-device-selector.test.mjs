import test from 'node:test';
import assert from 'node:assert/strict';
import { createGroupDeviceSelector } from '../../resources/js/group-device-selector.js';

const devices = [
    { key: 'z2:1', id: '1', name: 'Centro', type: 'z2', group_id: 1, group_name: 'Medellín', establishment_name: 'Oxxo Centro', business_type_id: 10, business_type_name: 'Oxxo' },
    { key: 'wl35:screen-35', id: 'screen-35', name: 'Norte', type: 'wl35', group_id: null, establishment_name: 'Oxxo Norte', business_type_id: 10, business_type_name: 'Oxxo' },
    { key: 'wl35:other', id: 'other', name: 'Éxito', type: 'wl35', group_id: 2, group_name: 'Cali', business_type_id: 20, business_type_name: 'Éxito' },
];

test('restores valid selections once and discloses existing group memberships', () => {
    const selector = createGroupDeviceSelector({ devices, selected: ['z2:1', 'wl35:screen-35', 'z2:1', 'missing'] });
    assert.deepEqual(selector.selected, ['z2:1', 'wl35:screen-35']);
    assert.deepEqual(selector.movedDevices.map(device => device.group_name), ['Medellín']);
});

test('combines search/model/business-type filters without losing hidden selections', () => {
    const selector = createGroupDeviceSelector({ devices, selected: ['z2:1'] });
    selector.model = 'wl35';
    selector.businessType = '10';
    selector.search = 'oxxo';
    assert.deepEqual(selector.filteredDevices.map(device => device.key), ['wl35:screen-35']);
    selector.selectVisible();
    selector.selectVisible();
    assert.deepEqual(selector.selected, ['z2:1', 'wl35:screen-35']);
    selector.search = 'Sin coincidencias';
    assert.equal(selector.filteredDevices.length, 0);
    assert.equal(selector.selected.length, 2);
    selector.clearSelection();
    assert.deepEqual(selector.selected, []);
});

test('accepts an empty catalog and searches a current group name', () => {
    assert.deepEqual(createGroupDeviceSelector().filteredDevices, []);
    const selector = createGroupDeviceSelector({ devices });
    selector.search = 'medellín';
    assert.deepEqual(selector.filteredDevices.map(device => device.key), ['z2:1']);
});
