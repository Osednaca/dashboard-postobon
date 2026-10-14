export const operatingLabels = {
    online: 'En línea', powered_off: 'Apagado', offline: 'Fuera de línea',
    unknown: 'Energía sin confirmar', unavailable: 'Estado no disponible',
};

export function createDeviceLive(rows = [], dependencies = {}) {
    const fetchCommand = dependencies.fetch || globalThis.fetch;
    const formData = dependencies.formData || ((form, submitter) => new FormData(form, submitter));
    const timeout = dependencies.setTimeout || globalThis.setTimeout;
    const clearTimeout = dependencies.clearTimeout || globalThis.clearTimeout;
    return {
        rows, stateFailed: false, commandBusy: false, commandMessage: '', commandFailed: false,
        operatingLabels,
        applySnapshot(snapshot) {
            this.stateFailed = snapshot.failed;
            const devices = new Map(snapshot.devices.map(device => [device.key, device]));
            this.rows = this.rows.map(row => {
                const device = devices.get(row.key);
                const operating = device?.operating;
                return {
                    ...row,
                    ...(operating || { status: 'unavailable', online: null, connected: null, powered: null,
                        bluetooth: null, sessionReady: null, uploading: null, videoCount: null }),
                    content: device?.current_video ?? null,
                    lastSeen: device?.last_seen ? new Date(device.last_seen).toLocaleString('es-CO') : 'Sin reporte',
                };
            });
        },
        canControl(row, command = 'power') {
            return !this.commandBusy && row.online === true && row.connected === true
                && (row.type !== 'wl35' || (row.sessionReady === true && row.uploading === false))
                && (command !== 'bluetooth' || row.bluetooth !== null);
        },
        async sendDeviceCommand(form, submitter = null) {
            if (this.commandBusy) return;
            this.commandBusy = true;
            this.commandFailed = false;
            this.commandMessage = 'Enviando orden…';
            const controller = new AbortController();
            const deadline = timeout(() => controller.abort(), 30000);
            try {
                const response = await fetchCommand(form.action, {
                    method: 'POST', body: formData(form, submitter), credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                const result = await response.json();
                if (!response.ok || result.success !== true) {
                    throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'No se pudo enviar la orden.');
                }
                this.commandMessage = result.message || 'Orden enviada. Esperando el estado reportado por el dispositivo.';
            } catch (error) {
                this.commandFailed = true;
                this.commandMessage = error.name === 'AbortError' || error instanceof SyntaxError || error instanceof TypeError
                    ? 'No se pudo confirmar el envío. Revisa el estado antes de reintentar.'
                    : error.message || 'No se pudo confirmar el envío. Revisa el estado antes de reintentar.';
            } finally {
                clearTimeout(deadline);
                this.commandBusy = false;
                // Only telemetry changes controls; acceptance of a command is not confirmation of power.
                this.$dispatch?.('refresh-devices');
            }
        },
    };
}
