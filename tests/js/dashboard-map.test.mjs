import test from 'node:test';
import assert from 'node:assert/strict';
import { validCoordinates, establishmentColor, createEstablishmentPopup, mountEstablishmentMap, setupDashboardMap } from '../../resources/js/dashboard-map.js';

function documentFixture(elements = {}) {
    return {
        baseURI: 'https://panel.test/dashboard',
        getElementById: id => elements[id],
        createElement: tag => ({
            tag, children: [], textContent: '',
            append(...children) { this.children.push(...children); },
            set innerHTML(value) { throw new Error(`Unsafe HTML insertion: ${value}`); },
        }),
    };
}

test('coordinates accept zero and reject incomplete, nonfinite and out of range values', () => {
    assert.equal(validCoordinates({ latitude: 0, longitude: 0 }), true);
    assert.equal(validCoordinates({ latitude: -90, longitude: 180 }), true);
    for (const [latitude, longitude] of [[null, 4], [1, undefined], [NaN, 1], [Infinity, 1], [91, 0], [0, -181], ['', 0]]) {
        assert.equal(validCoordinates({ latitude, longitude }), false);
    }
});

test('marker status summarizes connection independently of device power', () => {
    assert.equal(establishmentColor([{ status: 'online', power: false }, { status: 'unknown' }]), '#10B981');
    assert.equal(establishmentColor([{ status: 'offline' }, { status: 'unknown' }]), '#F59E0B');
    assert.equal(establishmentColor([{ status: 'offline' }]), '#64748B');
    assert.equal(establishmentColor([]), '#64748B');
});

test('popup renders assigned device names as text and links only same origin addresses', () => {
    const malicious = '<img src=x onerror=alert(1)>';
    const popup = createEstablishmentPopup({
        name: malicious, address: malicious,
        devices: [
            { name: malicious, type: 'z2', status: 'online', detail_url: '/devices/1' },
            { name: 'Caja', type: 'wl35', status: 'offline', detail_url: 'javascript:alert(1)' },
            { name: 'Entrada', type: 'wl35', status: 'unknown', detail_url: 'https://other.test/devices/2' },
        ],
    }, documentFixture());
    assert.equal(popup.children[0].textContent, malicious);
    assert.equal(popup.children[1].textContent, malicious);
    const items = popup.children[2].children;
    assert.equal(items.length, 3);
    assert.equal(items[0].children[0].tag, 'a');
    assert.equal(items[0].children[0].href, 'https://panel.test/devices/1');
    assert.equal(items[0].children[0].textContent, malicious);
    assert.equal(items[0].children[1].textContent, ' · Z2 · En línea');
    assert.equal(items[1].children[0].tag, 'span');
    assert.equal(items[1].children[1].textContent, ' · WL35 · Sin conexión');
    assert.equal(items[2].children[0].tag, 'span');
    assert.equal(items[2].children[1].textContent, ' · WL35 · Sin confirmar');
    const empty = createEstablishmentPopup({ name: 'Vacío', devices: [] }, documentFixture());
    assert.equal(empty.children[2].textContent, 'Sin dispositivos asignados');
});

test('one marker per establishment groups devices and fits all valid coordinates', () => {
    const markers = [];
    const map = { setView() { return this; }, fitBounds(bounds, options) { this.bounds = bounds; this.options = options; } };
    const leaflet = {
        map: () => map,
        tileLayer: () => ({ addTo: () => {} }),
        circleMarker: (coordinates, options) => ({
            addTo() { markers.push({ coordinates, options }); return this; },
            bindPopup(popup) { markers.at(-1).popup = popup; },
        }),
    };
    mountEstablishmentMap({}, [
        { name: 'Centro', latitude: 0, longitude: 0, devices: [
            { name: 'A', type: 'z2', status: 'online' }, { name: 'B', type: 'wl35', status: 'offline' },
        ] },
        { name: 'Otra', latitude: 5, longitude: -74, devices: [] },
        { name: 'Sin ubicación', latitude: null, longitude: null, devices: [] },
    ], leaflet, documentFixture());
    assert.equal(markers.length, 2);
    assert.equal(markers[0].popup.children[2].children.length, 2);
    assert.deepEqual(map.bounds, [[0, 0], [5, -74]]);
    assert.equal(map.options.maxZoom, 14);
});

test('unavailable map dependency has a visible recovery message', () => {
    const message = { hidden: true };
    setupDashboardMap(documentFixture({ 'device-map': {}, 'dashboard-map-data': { textContent: '[]' }, 'dashboard-map-message': message }), null);
    assert.equal(message.hidden, false);
    assert.match(message.textContent, /No se pudo cargar el mapa/);
    setupDashboardMap(documentFixture(), null);
});
