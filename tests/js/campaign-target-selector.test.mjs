import test from 'node:test';
import assert from 'node:assert/strict';
import { createCampaignTargetSelector } from '../../resources/js/campaign-target-selector.js';

const catalog = [
    { key: 'z2:AABB', name: 'Equipo Bogotá', type: 'z2', city: 'Bogotá', group_id: 1, group_name: 'Norte' },
    { key: 'wl35:fan-cali', name: 'Equipo Cali', type: 'wl35', city: 'Cali', group_id: 2, group_name: 'Sur' },
    { key: 'wl35:fan-none', name: 'Sin ciudad', type: 'wl35', city: '', group_id: null, group_name: null },
];

test('city and group filters preserve recipients from multiple cities without selecting others', () => {
    const selector = createCampaignTargetSelector(catalog);
    selector.city = 'Bogotá';
    selector.toggle(selector.visible[0].key);
    selector.city = 'Cali';
    assert.deepEqual(selector.selected, ['z2:AABB']);
    selector.toggle(selector.visible[0].key);
    selector.group = '1';
    assert.equal(selector.visible.length, 0);
    assert.deepEqual(selector.selected, ['z2:AABB', 'wl35:fan-cali']);
    selector.city = '';
    selector.group = '';
    assert.equal(selector.visible.length, 3);
    assert.deepEqual(selector.selected, ['z2:AABB', 'wl35:fan-cali']);
});

test('restores missing identities without silently expanding or dropping the selection', () => {
    const selector = createCampaignTargetSelector(catalog, ['z2:AABB', 'z2:AABB', 'wl35:removed']);
    assert.deepEqual(selector.selected, ['z2:AABB', 'wl35:removed']);
    assert.match(selector.label('wl35:removed'), /no disponible/);
    selector.remove('wl35:removed');
    selector.toggle('unknown');
    assert.deepEqual(selector.selected, ['z2:AABB']);
    selector.toggle('z2:AABB');
    assert.deepEqual(selector.selected, []);
    assert.deepEqual(createCampaignTargetSelector(catalog, {}).selected, []);
});

test('devices without a city stay selectable and group options are unique', () => {
    const selector = createCampaignTargetSelector([...catalog, { ...catalog[0], key: 'z2:CCDD' }]);
    selector.city = '__none__';
    assert.deepEqual(selector.visible.map(device => device.key), ['wl35:fan-none']);
    selector.toggle('wl35:fan-none');
    assert.match(selector.label('wl35:fan-none'), /Sin ciudad/);
    assert.deepEqual(selector.groups.map(group => group.id), ['1', '2']);
});
