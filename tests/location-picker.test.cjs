const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function picker(initial = { latitude: null, longitude: null }) {
  const template = fs.readFileSync(path.join(__dirname, '../resources/views/mobile/location-picker.blade.php'), 'utf8');
  const source = template.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/const nonce = .*?;/, 'const nonce = "test-nonce";')
    .replace(/const initial = .*?;/, `const initial = ${JSON.stringify(initial)};`);
  const elements = Object.fromEntries(['address', 'status', 'confirm', 'map', 'place-search'].map(id => [id, {
    value: '', disabled: true, listeners: {}, addEventListener(name, callback) { this.listeners[name] = callback; },
    appendChild() {},
  }]));
  const messages = [], pending = [], objects = {};
  const google = { maps: {
    Map: class { constructor() { objects.map = this; this.listeners = {}; } addListener(name, fn) { this.listeners[name] = fn; } panTo() {} setZoom() {} },
    Marker: class { constructor() { objects.marker = this; this.listeners = {}; } addListener(name, fn) { this.listeners[name] = fn; } setMap() {} setPosition() {} },
    Geocoder: class { geocode() { return new Promise((resolve, reject) => pending.push({ resolve, reject })); } },
    places: { PlaceAutocompleteElement: class { constructor() { objects.autocomplete = this; this.listeners = {}; } addEventListener(name, fn) { this.listeners[name] = fn; } setAttribute() {} } },
  } };
  const window = { ReactNativeWebView: { postMessage: data => messages.push(JSON.parse(data)) } };
  vm.runInNewContext(source, { window, google, document: { getElementById: id => elements[id] } });
  window.initLocationPicker();
  const point = (lat, lng) => ({ lat: () => lat, lng: () => lng });
  return { elements, messages, pending, objects, point };
}

test('initial map is not a selection; Places New fetches location and confirms only on tap', async () => {
  const p = picker();
  assert.equal(p.elements.confirm.disabled, true);
  assert.deepEqual(p.messages, []);
  const place = { location: p.point(4.5, -74), formattedAddress: 'Calle 1', fetchFields: async ({ fields }) => {
    assert.equal(JSON.stringify(fields), JSON.stringify(['formattedAddress', 'location']));
  } };
  await p.objects.autocomplete.listeners['gmp-select']({ placePrediction: { toPlace: () => place } });
  assert.equal(p.elements.confirm.disabled, false);
  assert.deepEqual(p.messages, []);
  p.elements.confirm.listeners.click();
  assert.deepEqual(p.messages[0], { nonce: 'test-nonce', type: 'location-selected', address: 'Calle 1', latitude: 4.5, longitude: -74 });
});

test('Place details failures and stale results never confirm missing or replaced locations', async () => {
  const p = picker();
  await p.objects.autocomplete.listeners['gmp-select']({ placePrediction: { toPlace: () => ({ fetchFields: async () => { throw new Error('denied'); } }) } });
  assert.equal(p.elements.confirm.disabled, true);
  assert.equal(p.messages[0].type, 'location-error');
  let resolve;
  const details = p.objects.autocomplete.listeners['gmp-select']({ placePrediction: { toPlace: () => ({
    location: p.point(1, 1), formattedAddress: 'Obsoleto', fetchFields: () => new Promise(done => { resolve = done; }),
  }) } });
  p.objects.map.listeners.click({ latLng: p.point(7, -70) });
  p.pending[0].resolve({ results: [{ formatted_address: 'Punto actual' }] });
  resolve();
  await details;
  await new Promise(done => setImmediate(done));
  p.elements.confirm.listeners.click();
  assert.equal(p.messages[1].latitude, 7);
  assert.equal(p.messages[1].address, 'Punto actual');
});

test('map and drag wait for reverse lookup; failed lookup permits explicit manual address without stale text', async () => {
  const p = picker({ latitude: 4, longitude: -74 });
  p.elements.address.value = 'Dirección anterior';
  p.objects.map.listeners.click({ latLng: p.point(5, -75) });
  assert.equal(p.elements.address.value, '');
  assert.equal(p.elements.confirm.disabled, true);
  p.elements.confirm.listeners.click();
  assert.deepEqual(p.messages, []);
  p.pending[0].reject(new Error('offline'));
  await new Promise(resolve => setImmediate(resolve));
  p.elements.address.value = 'Dirección confirmada del punto';
  p.elements.address.listeners.input();
  assert.equal(p.elements.confirm.disabled, false);
  p.elements.confirm.listeners.click();
  assert.equal(p.messages[0].latitude, 5);
  p.objects.marker.listeners.dragend({ latLng: p.point(6, -76) });
  assert.equal(p.elements.confirm.disabled, true);
  p.pending[1].resolve({ results: [{ formatted_address: 'Nueva dirección' }] });
  await new Promise(resolve => setImmediate(resolve));
  p.elements.confirm.listeners.click();
  assert.equal(p.messages[1].address, 'Nueva dirección');
  assert.equal(p.messages[1].latitude, 6);
});

test('older reverse lookup cannot overwrite a newer selection and typed search cannot confirm old coordinates', async () => {
  const p = picker();
  p.objects.map.listeners.click({ latLng: p.point(5, -75) });
  p.objects.map.listeners.click({ latLng: p.point(6, -76) });
  p.pending[1].resolve({ results: [{ formatted_address: 'Actual' }] });
  p.pending[0].resolve({ results: [{ formatted_address: 'Vieja' }] });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(p.elements.address.value, 'Actual');
  p.elements.address.value = 'Buscando otro sitio';
  p.elements.address.listeners.input();
  assert.equal(p.elements.confirm.disabled, true);
});
