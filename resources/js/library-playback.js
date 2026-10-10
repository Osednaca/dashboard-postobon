export function createLibraryPlayback(statusTemplate, dependencies = {}) {
    const request = dependencies.fetch ?? globalThis.fetch;
    const schedule = dependencies.setInterval ?? globalThis.setInterval;
    const cancel = dependencies.clearInterval ?? globalThis.clearInterval;
    let storage = dependencies.storage;
    if (!storage) { try { storage = globalThis.localStorage; } catch { /* Storage can be blocked by the browser. */ } }
    const formData = dependencies.formData ?? ((form) => new FormData(form));
    const persist = (callback) => { try { callback(storage); } catch { /* Recovery storage is optional. */ } };
    let poller;
    let polling = false;
    let destroyed = false;

    return {
        id: null,
        status: 'idle',
        progress: 0,
        result: null,
        message: '',
        get busy() { return ['dispatching', 'queued', 'processing', 'session_expired'].includes(this.status); },
        async submit(event) {
            if (this.busy || destroyed) return;
            this.status = 'dispatching';
            this.result = null;
            this.message = 'Preparando reproducción…';
            this.progress = 5;
            try {
                const response = await request(event.currentTarget.action, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData(event.currentTarget),
                });
                const payload = await response.json();
                if (destroyed) return;
                if (!response.ok || !payload.upload_id) throw new Error('dispatch rejected');
                this.id = payload.upload_id;
                this.status = 'queued';
                this.message = 'Reproducción en cola.';
                persist((store) => store?.setItem('instant-play-upload-id', this.id));
                cancel(poller);
                poller = schedule(() => this.poll(), 2500);
                await this.poll();
            } catch {
                if (!destroyed) {
                    this.status = 'failed';
                    this.message = 'No fue posible iniciar la reproducción. Inténtalo nuevamente o avisa al administrador.';
                }
            }
        },
        async poll() {
            if (!this.id || polling || destroyed) return;
            polling = true;
            try {
                const response = await request(statusTemplate.replace('__UPLOAD_ID__', encodeURIComponent(this.id)), {
                    credentials: 'same-origin', headers: { Accept: 'application/json' },
                });
                if (destroyed) return;
                if (response.status === 401) {
                    this.status = 'session_expired';
                    this.message = 'Tu sesión caducó. Inicia sesión y consulta esta operación en reproducción instantánea antes de enviar otra orden.';
                    cancel(poller);
                    return;
                }
                if ([403, 404].includes(response.status)) {
                    this.status = 'failed';
                    this.message = 'No se pudo recuperar la operación. Inicia una nueva reproducción.';
                    cancel(poller);
                    return;
                }
                if (!response.ok) throw new Error('status unavailable');
                const payload = await response.json();
                if (destroyed) return;
                this.status = payload.status;
                this.progress = payload.progress ?? this.progress;
                this.result = payload.result;
                this.message = this.status === 'completed'
                    ? `Órdenes enviadas: ${payload.result?.succeeded ?? 0}. Fallidas: ${payload.result?.failed ?? 0}.`
                    : this.status === 'failed' ? 'No fue posible completar la reproducción. Revisa el archivo e inténtalo nuevamente.'
                        : 'Enviando el video seleccionado…';
                if (['completed', 'failed'].includes(this.status)) {
                    cancel(poller);
                    persist((store) => { if (store?.getItem('instant-play-upload-id') === this.id) store.removeItem('instant-play-upload-id'); });
                }
            } catch {
                if (!destroyed) this.message = 'No se pudo actualizar el progreso. Se volverá a consultar; la operación puede continuar.';
            } finally {
                polling = false;
            }
        },
        destroy() {
            destroyed = true;
            cancel(poller);
        },
    };
}
