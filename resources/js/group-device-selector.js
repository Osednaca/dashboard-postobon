export function createGroupDeviceSelector({ devices = [], selected = [] } = {}) {
    const catalog = Array.isArray(devices) ? devices : [];
    const keys = new Set(catalog.map(device => device.key));

    return {
        devices: catalog,
        selected: [...new Set((Array.isArray(selected) ? selected : []).filter(key => keys.has(key)))],
        search: '',
        model: '',
        businessType: '',

        get filteredDevices() {
            const query = this.search.trim().toLocaleLowerCase('es');

            return this.devices.filter(device =>
                (!this.model || device.type === this.model)
                && (!this.businessType || String(device.business_type_id) === String(this.businessType))
                && (!query || [device.name, device.id, device.establishment_name, device.group_name, device.business_type_name]
                    .filter(Boolean).join(' ').toLocaleLowerCase('es').includes(query)));
        },

        get movedDevices() {
            return this.devices.filter(device => this.selected.includes(device.key) && device.group_id != null);
        },

        selectVisible() {
            this.selected = [...new Set([...this.selected, ...this.filteredDevices.map(device => device.key)])];
        },

        clearSelection() {
            this.selected = [];
        },
    };
}
