import test from 'node:test';
import assert from 'node:assert/strict';
import { createMediaUpload } from '../../resources/js/media-upload.js';

function setup() {
    const handlers = {};
    const uploadHandlers = {};
    const calls = [];
    const request = {
        upload: { addEventListener: (name, fn) => { uploadHandlers[name] = fn; } },
        open: (...args) => calls.push(['open', ...args]),
        setRequestHeader: (...args) => calls.push(['header', ...args]),
        addEventListener: (name, fn) => { handlers[name] = fn; },
        send: (data) => calls.push(['send', data]),
    };
    const navigated = [];
    const state = createMediaUpload({ makeRequest: () => request, makeFormData: () => 'form-data', navigate: (url) => navigated.push(url) });
    state.$refs = { fileInput: { files: [], value: '' } };
    return { state, request, handlers, uploadHandlers, calls, navigated };
}
const event = { currentTarget: { action: '/media' } };

test('dropped file is assigned to the form and transfer progress transitions to server processing', () => {
    const { state, request, handlers, uploadHandlers, calls, navigated } = setup();
    const files = [{ name: 'video.mp4' }];
    state.handleDrop(files);
    assert.equal(state.$refs.fileInput.files, files);
    state.submit(event);
    state.submit(event);
    assert.equal(calls.filter(call => call[0] === 'send').length, 1);
    uploadHandlers.progress({ lengthComputable: true, loaded: 30, total: 100 });
    assert.equal(state.progress, 30);
    uploadHandlers.load();
    assert.equal(state.progress, 100);
    assert.equal(state.processing, true);
    request.status = 200;
    request.responseText = JSON.stringify({ redirect: '/media' });
    handlers.load();
    assert.deepEqual(navigated, ['/media']);
    assert.equal(state.isUploading, true);
});

test('validation and connection failures permit retry and present meaningful feedback', () => {
    const { state, request, handlers } = setup();
    state.submit(event);
    assert.match(state.error, /Selecciona/);
    state.handleFile({ name: 'video.mp4' });
    state.submit(event);
    request.status = 422;
    request.responseText = JSON.stringify({ errors: { file: ['Archivo inválido.'] } });
    handlers.load();
    assert.equal(state.error, 'Archivo inválido.');
    assert.equal(state.isUploading, false);
    state.submit(event);
    handlers.error();
    assert.match(state.error, /Comprueba la biblioteca/);
    assert.equal(state.isUploading, false);
    state.clearFile();
    assert.equal(state.file, null);
});

test('handles non-JSON server failures without reporting success', () => {
    const { state, request, handlers, navigated } = setup();
    state.handleFile({ name: 'video.mp4' });
    state.submit(event);
    request.status = 500;
    request.responseText = '<html>Error</html>';
    handlers.load();
    assert.match(state.error, /No se pudo/);
    assert.equal(state.isUploading, false);
    assert.deepEqual(navigated, []);
});
