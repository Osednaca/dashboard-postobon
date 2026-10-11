import test from 'node:test';
import assert from 'node:assert/strict';
import { createCampaignMediaSelector } from '../../resources/js/campaign-media-selector.js';
import { createMediaDurationStore, createMediaPreview } from '../../resources/js/media-preview.js';

const catalog = [{ id: 1, name: 'Principal' }, { id: 2, name: 'Secundario' }, { id: 3, name: 'Cierre' }];

test('restores order, normalizes IDs and preserves missing selections so they can be removed', () => {
    const selector = createCampaignMediaSelector(catalog, ['2', 1, '2', 99, null, {}, 'bad']);
    assert.deepEqual(selector.selected, [2, 1, 99]);
    assert.equal(selector.name(99), 'Medio no disponible (#99)');
    assert.equal(selector.available(99), false);
    selector.remove(99);
    assert.deepEqual(selector.selected, [2, 1]);
    assert.deepEqual(createCampaignMediaSelector(catalog, 'invalid').selected, []);
});

test('add and remove are explicit and cannot duplicate or invent library items', () => {
    const selector = createCampaignMediaSelector(catalog);
    selector.add('2');
    selector.add(2);
    selector.add(999);
    selector.add(1);
    assert.deepEqual(selector.selected, [2, 1]);
    selector.remove('2');
    assert.deepEqual(selector.selected, [1]);
    selector.remove(1);
    assert.deepEqual(selector.selected, []);
});

test('moves one step in either direction and protects the first and last positions', () => {
    const selector = createCampaignMediaSelector(catalog, [1, 2, 3]);
    selector.move(1, -1);
    selector.move(3, 1);
    selector.move(999, 1);
    selector.move(1, 2);
    assert.deepEqual(selector.selected, [1, 2, 3]);
    selector.move(3, -1);
    assert.deepEqual(selector.selected, [1, 3, 2]);
    selector.move(1, 1);
    assert.deepEqual(selector.selected, [3, 1, 2]);
});

test('preview metadata, errors, retries and destruction leave selection and order intact', () => {
    const selector = createCampaignMediaSelector(catalog, [2, 1]);
    const preview = createMediaPreview('1', '/media/1/content', 'video');
    const video = { duration: 28, readyState: 4, pause() {}, load() {}, removeAttribute() {} };
    preview.$refs = { video };
    preview.$store = { mediaDurations: createMediaDurationStore() };
    preview.$nextTick = (callback) => callback();
    preview.start();
    preview.metadata(video);
    preview.ready();
    selector.move(1, -1);
    assert.equal(preview.source, '/media/1/content');
    assert.equal(video.currentTime, 0.1);
    preview.fail();
    preview.retry();
    preview.destroy();
    assert.deepEqual(selector.selected, [1, 2]);
});
