export function createCampaignTargetSelector(catalog = [], initial = [], criteria = {}, options = {}) {
    const entries = new Map(catalog.map((device) => [device.key, device]));
    return {
        mode: ['manual', 'segment', 'establishment', 'all'].includes(criteria.mode) ? criteria.mode : 'manual',
        businessTypes: (Array.isArray(criteria.businessTypes) ? criteria.businessTypes : []).map(String),
        segmentGroups: (Array.isArray(criteria.groups) ? criteria.groups : []).map(String),
        establishment: String(criteria.establishment ?? ''),
        businessTypeOptions: options.businessTypes ?? [],
        segmentGroupOptions: options.groups ?? [],
        establishmentOptions: options.establishments ?? [],
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
        get recipients() {
            if (this.mode === 'manual') return this.selected;
            if (this.mode === 'all') return [...entries.keys()];
            if (this.mode === 'establishment') {
                return this.establishment ? [...entries.values()].filter((device) => device.establishment_id != null
                    && String(device.establishment_id) === this.establishment).map((device) => device.key) : [];
            }
            if (!this.businessTypes.length && !this.segmentGroups.length) return [];
            return [...entries.values()].filter((device) => (!this.businessTypes.length || (device.business_type_id != null
                && this.businessTypes.includes(String(device.business_type_id))))
                && (!this.segmentGroups.length || (device.group_id != null && this.segmentGroups.includes(String(device.group_id)))))
                .map((device) => device.key);
        },
        has(key) { return this.selected.includes(key); },
        toggle(key) {
            if (this.has(key)) this.remove(key);
            else if (entries.has(key)) this.selected.push(key);
        },
        remove(key) { this.selected = this.selected.filter((value) => value !== key); },
        label(key) {
            const device = entries.get(key);
            return device ? `${device.name} · ${device.type === 'wl35' ? '35' : '42'} · ${device.city || 'Sin ciudad'}` : `Dispositivo no disponible (${key})`;
        },
    };
}
