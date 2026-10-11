export function createCampaignTargetSelector(catalog = [], initial = []) {
    const entries = new Map(catalog.map((device) => [device.key, device]));
    return {
        selected: [...new Set((Array.isArray(initial) ? initial : []).filter((key) => typeof key === 'string'))],
        city: '',
        group: '',
        cities: [...new Set(catalog.map((device) => device.city).filter(Boolean))].sort(),
        groups: [...new Map(catalog.filter((device) => device.group_id !== null)
            .map((device) => [String(device.group_id), { id: String(device.group_id), name: device.group_name }])).values()],
        get visible() {
            return catalog.filter((device) => (!this.city || (this.city === '__none__' ? !device.city : device.city === this.city))
                && (!this.group || String(device.group_id) === this.group));
        },
        has(key) { return this.selected.includes(key); },
        toggle(key) {
            if (this.has(key)) this.remove(key);
            else if (entries.has(key)) this.selected.push(key);
        },
        remove(key) { this.selected = this.selected.filter((value) => value !== key); },
        label(key) {
            const device = entries.get(key);
            return device ? `${device.name} · ${device.type.toUpperCase()} · ${device.city || 'Sin ciudad'}` : `Dispositivo no disponible (${key})`;
        },
    };
}
