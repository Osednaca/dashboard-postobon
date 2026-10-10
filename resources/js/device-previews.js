import { initiallyVisible, observePreviewVisibility } from './preview-visibility.js';

export const previewLabels = {
    ready: 'Video actual',
    offline: 'Fuera de línea',
    powered_off: 'Ventilador apagado',
    unknown: 'Esperando estado del ventilador',
    unavailable: 'Estado no disponible',
    no_content: 'Sin video en reproducción',
    missing_mapping: 'Sin copia identificada del video actual',
    missing_file: 'Archivo de video no disponible',
};

export function applyVideoSource(video, url) {
    const next = url || null;
    if (video.getAttribute('src') === next) return false;
    video.pause();
    if (next) video.setAttribute('src', next);
    else video.removeAttribute('src');
    video.load();
    return true;
}

export function createPreviewPlayer() {
    let stopObserving;
    return {
        source: null,
        error: false,
        inViewport: initiallyVisible(),
        resumePlayback: false,
        destroyed: false,
        init() {
            this.$nextTick(() => {
                if (this.destroyed) return;
                stopObserving = observePreviewVisibility(this.$refs.video.parentElement, visible => {
                    this.inViewport = visible;
                    this.syncPlayback();
                });
                this.update(this.device);
                this.$watch('device', device => this.update(device));
                this.$watch('visible', () => this.syncPlayback());
            });
        },
        update(device) {
            if (this.destroyed) return;
            const next = device.status === 'ready' ? device.url : null;
            if (next !== this.source) {
                this.error = false;
                this.resumePlayback = false;
                // Release old content immediately, but defer the new request until visible.
                if (!this.inViewport || !this.visible) applyVideoSource(this.$refs.video, null);
            }
            this.source = next;
            if (!next) applyVideoSource(this.$refs.video, null);
            this.syncPlayback();
        },
        syncPlayback() {
            if (this.destroyed) return;
            const video = this.$refs.video;
            if (!this.inViewport || !this.visible) {
                this.resumePlayback ||= !video.paused && !video.ended;
                video.pause();
                return;
            }
            if (!this.source || this.error) return;
            const changed = applyVideoSource(video, this.source);
            if (changed || this.resumePlayback) {
                this.resumePlayback = false;
                video.play()?.catch(() => {});
            }
        },
        failed() {
            if (this.source) {
                this.error = true;
                this.resumePlayback = false;
                this.$refs.video.pause();
            }
        },
        retry() {
            this.error = false;
            applyVideoSource(this.$refs.video, null);
            this.update(this.device);
        },
        destroy() {
            this.destroyed = true;
            stopObserving?.();
            applyVideoSource(this.$refs.video, null);
        },
    };
}

export function createDevicePreviews(endpoint, deviceKey = null, dependencies = {}) {
    const fetchSnapshot = dependencies.fetch || globalThis.fetch;
    const doc = dependencies.document || globalThis.document;
    const schedule = dependencies.setInterval || globalThis.setInterval;
    const cancel = dependencies.clearInterval || globalThis.clearInterval;
    let timer, visibilityListener, controller;
    let stopped = false;

    return {
        devices: [],
        loading: true,
        updating: false,
        failed: false,
        visible: !doc.hidden,
        labels: previewLabels,
        init() {
            this.refresh();
            timer = schedule(() => this.refresh(), 10000);
            visibilityListener = async () => {
                if (doc.hidden) this.visible = false;
                else {
                    await this.refresh();
                    if (!stopped) this.visible = true;
                }
            };
            doc.addEventListener('visibilitychange', visibilityListener);
        },
        async refresh() {
            if (stopped || doc.hidden || this.updating) return;
            this.updating = true;
            controller = new AbortController();
            try {
                const response = await fetchSnapshot(endpoint, {
                    headers: { Accept: 'application/json' }, credentials: 'same-origin',
                    cache: 'no-store', signal: controller.signal,
                });
                if (!response.ok) throw new Error('Preview unavailable');
                const snapshot = await response.json();
                if (!Array.isArray(snapshot.devices)) throw new Error('Invalid preview response');
                if (!stopped) {
                    this.devices = snapshot.devices.filter(device => !deviceKey || device.key === deviceKey);
                    this.failed = false;
                }
            } catch {
                if (!stopped) {
                    this.failed = true;
                    this.devices = this.devices.map(device => ({ ...device, status: 'unavailable', url: null }));
                }
            } finally {
                this.updating = false;
                this.loading = false;
            }
        },
        destroy() {
            stopped = true;
            cancel(timer);
            controller?.abort();
            doc.removeEventListener('visibilitychange', visibilityListener);
        },
    };
}
