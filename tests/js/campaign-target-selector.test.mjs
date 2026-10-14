import test from 'node:test';
import assert from 'node:assert/strict';
import { createCampaignTargetSelector } from '../../resources/js/campaign-target-selector.js';

const catalog = [
    { key: 'z2:AABB', name: 'Equipo Bogotá', type: 'z2', city: 'Bogotá', group_id: 1, group_name: 'Norte' },
    { key: 'wl35:fan-cali', name: 'Equipo Cali', type: 'wl35', city: 'Cali', group_id: 2, group_name: 'Sur' },
    { key: 'wl35:fan-none', name: 'Sin ciudad', type: 'wl35', city: '', group_id: null, group_name: null },
];

test('displayed model labels change while original identities and user names remain intact', () => {
    const selector = createCampaignTargetSelector([
        { ...catalog[0], name: 'Z2 recepción' },
        { ...catalog[1], name: 'WL35 entrada' },
    ]);
    assert.equal(selector.label('z2:AABB'), 'Z2 recepción · 42 · Bogotá');
    assert.equal(selector.label('wl35:fan-cali'), 'WL35 entrada · 35 · Cali');
    selector.toggle('z2:AABB');
    selector.toggle('wl35:fan-cali');
    assert.deepEqual(selector.selected, ['z2:AABB', 'wl35:fan-cali']);
    assert.equal(selector.visible[0].type, 'z2');
    assert.equal(selector.visible[1].type, 'wl35');
});

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

const audienceCatalog = [
    { ...catalog[0], group_id: 1, business_type_id: 10, establishment_id: 100 },
    { ...catalog[1], group_id: 1, business_type_id: 10, establishment_id: 100 },
    { ...catalog[0], key: 'z2:OTHER-CITY', group_id: 2, business_type_id: 10, establishment_id: 101 },
    { ...catalog[0], key: 'z2:EXITO-80', group_id: 1, business_type_id: 20, establishment_id: 200 },
    { ...catalog[0], key: 'z2:EXITO-OTHER', group_id: 1, business_type_id: 20, establishment_id: 201 },
    { ...catalog[2], group_id: 1, business_type_id: null, establishment_id: null },
];

test('structured preview intersects business type and group and deduplicates identities', () => {
    const selector = createCampaignTargetSelector([...audienceCatalog, audienceCatalog[0]], [], {
        mode: 'segment', businessTypes: [10], groups: [1],
    });
    assert.deepEqual(selector.recipients, ['z2:AABB', 'wl35:fan-cali']);
    selector.businessTypes = ['10', '20'];
    assert.deepEqual(selector.recipients, ['z2:AABB', 'wl35:fan-cali', 'z2:EXITO-80', 'z2:EXITO-OTHER']);
    selector.segmentGroups = ['1', '2'];
    assert.equal(selector.recipients.length, 5);
    selector.segmentGroups = [];
    selector.businessTypes = [];
    assert.deepEqual(selector.recipients, []);
});

test('exact establishment preview ignores other criteria and excludes other same-type stores', () => {
    const selector = createCampaignTargetSelector(audienceCatalog, ['wl35:fan-none'], {
        mode: 'establishment', establishment: 200, businessTypes: [10], groups: [2],
    });
    assert.deepEqual(selector.recipients, ['z2:EXITO-80']);
    selector.establishment = '999';
    assert.deepEqual(selector.recipients, []);
    selector.establishment = '';
    assert.deepEqual(selector.recipients, []);
    selector.mode = 'manual';
    assert.deepEqual(selector.recipients, ['wl35:fan-none']);
    selector.city = 'Bogotá';
    assert.deepEqual(selector.recipients, ['wl35:fan-none']);
});
