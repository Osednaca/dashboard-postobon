import { createDeviceLive } from './device-live.js';

export function createDeviceList(rows = [], dependencies = {}) {
    const request = dependencies.fetch || globalThis.fetch;
    return {
        ...createDeviceLive(rows),
        statusFilter: '', protocolFilter: '', establishmentFilter: '', search: '',
        actionKey: null, actionPosition: {}, actionTrigger: null,
        playbackTargets: [], playbackName: '', dialogTrigger: null,
        activeGroupId: '', showVideoRemovalModal: false,
        removalTargets: [], removalMediaId: '', removalBusy: false,
        removalResults: [], removalError: '', removalAttempted: false,
        matches(row) {
            const query = this.search.trim().toLocaleLowerCase();
            return (!query || row.searchable.toLocaleLowerCase().includes(query))
                && (!this.protocolFilter || row.type === this.protocolFilter)
                && (!this.statusFilter || row.status === this.statusFilter)
                && (!this.establishmentFilter || row.establishmentId === this.establishmentFilter);
        },
        visibleRows() { return this.rows.filter(row => this.matches(row)); },
        countStatus(status) { return this.visibleRows().filter(row => row.status === status).length; },
        visibleSelectedRows(keys = this.selectedFleetKeys) {
            const selected = new Set(keys);
            return this.visibleRows().filter(row => selected.has(row.key));
        },
        openVideoRemoval(keys = this.selectedFleetKeys) {
            if (this.removalBusy) return;
            this.removalTargets = this.visibleSelectedRows(keys)
                .filter(row => row.removalKey)
                .map(row => ({ key: row.removalKey, name: row.name, identifier: row.identifier }));
            if (!this.removalTargets.length) return;
            this.removalMediaId = '';
            this.removalResults = [];
            this.removalError = '';
            this.removalAttempted = false;
            this.closeActions(false);
            this.showVideoRemovalModal = true;
        },
        closeVideoRemoval() {
            if (!this.removalBusy) this.showVideoRemovalModal = false;
        },
        async submitVideoRemoval(form) {
            if (this.removalBusy || this.removalAttempted || !this.removalMediaId || !this.removalTargets.length) return;
            this.removalBusy = true;
            this.removalAttempted = true;
            this.removalError = '';
            try {
                const response = await request(form.action, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: JSON.stringify({ media_id: this.removalMediaId,
                        device_keys: this.removalTargets.map(target => target.key), group_id: this.activeGroupId || null }),
                });
                const payload = await response.json();
                if (!response.ok) {
                    // Validation happens before dispatch, so it is safe to correct the selection.
                    if (response.status === 422) this.removalAttempted = false;
                    throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'No se pudo confirmar la solicitud.');
                }
                if (!Array.isArray(payload.results)) throw new Error('No se pudo confirmar la respuesta. Revisa los equipos antes de volver a solicitarlo.');
                this.removalResults = payload.results;
            } catch (error) {
                this.removalError = error instanceof TypeError || error instanceof SyntaxError
                    ? 'Se perdió la respuesta. Revisa los equipos antes de volver a solicitarlo.'
                    : error.message;
            } finally {
                this.removalBusy = false;
                this.$dispatch?.('refresh-devices');
            }
        },
        resetFilters() { this.search = ''; this.protocolFilter = ''; this.statusFilter = ''; this.establishmentFilter = ''; },
        focusAfterRender(resolveControl) {
            this.$nextTick(() => window.requestAnimationFrame(() => {
                const control = resolveControl();
                if (control?.getClientRects().length) control.focus();
            }));
        },
        openActions(key, trigger) {
            this.actionTrigger = trigger;
            const rect = trigger.getBoundingClientRect();
            this.actionPosition = { top: `${Math.max(12, Math.min(rect.bottom + 8, window.innerHeight - 440))}px`, right: `${Math.max(12, window.innerWidth - rect.right)}px` };
            this.actionKey = key;
            this.focusAfterRender(() => {
                const menu = document.getElementById(trigger.getAttribute('aria-controls'));
                return menu?.querySelector('button');
            });
        },
        closeActions(restoreFocus = true) {
            this.actionKey = null;
            if (restoreFocus) {
                this.actionTrigger?.focus();
                this.actionTrigger = null;
            }
        },
        openPlayback(targets = this.selectedFleetKeys, name = 'la selección') {
            this.playbackTargets = [...targets];
            this.playbackName = name;
            this.closeActions(false);
            this.showFleetPlayModal = true;
        },
        watchDialogs() {
            for (const name of ['showFleetPlayModal', 'showDeleteModal', 'showBulkAssignMediaModal', 'showFleetUploadModal', 'showFleetFormatModal', 'showVideoRemovalModal']) {
                this.$watch(name, open => {
                    if (open) {
                        this.dialogTrigger = this.actionTrigger || document.activeElement;
                        this.focusAfterRender(() => {
                            const controls = this.$root.querySelectorAll(`[data-dialog="${name}"] button, [data-dialog="${name}"] select`);
                            return [...controls].find(element => !element.disabled && element.getClientRects().length);
                        });
                    } else {
                        this.dialogTrigger?.focus();
                        this.actionTrigger = null;
                    }
                });
            }
        },
        dialogKeydown(event) {
            const dialog = event.target.closest('[role="dialog"]');
            if (!dialog) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                if (this.actionKey) this.closeActions();
                else if (this.showVideoRemovalModal) this.closeVideoRemoval();
                else if (this.showFleetPlayModal) this.showFleetPlayModal = false;
                else if (this.showDeleteModal) this.showDeleteModal = false;
                else if (this.showBulkAssignMediaModal) this.showBulkAssignMediaModal = false;
                else if (this.showFleetUploadModal) this.dismissFleetUpload();
                else if (this.showFleetFormatModal) this.dismissFleetFormat();
            }
            if (event.key !== 'Tab') return;
            const controls = [...dialog.querySelectorAll('a[href], button, input, select, summary, [tabindex="0"]')]
                .filter(element => !element.disabled && element.offsetParent !== null && element.type !== 'hidden');
            const first = controls[0], last = controls.at(-1);
            if ((event.shiftKey && event.target === first) || (!event.shiftKey && event.target === last)) {
                event.preventDefault();
                (event.shiftKey ? last : first)?.focus();
            }
        },
    };
}
