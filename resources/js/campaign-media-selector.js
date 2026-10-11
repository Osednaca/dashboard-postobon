export function createCampaignMediaSelector(catalog = [], initial = []) {
    const names = new Map(catalog.map((media) => [Number(media.id), media.name]));
    const selected = [...new Set((Array.isArray(initial) ? initial : [])
        .filter((id) => typeof id === 'number' || typeof id === 'string')
        .map(Number).filter((id) => Number.isSafeInteger(id) && id > 0))];

    return {
        selected,
        has(id) { return this.selected.includes(Number(id)); },
        name(id) { return names.get(Number(id)) ?? `Medio no disponible (#${id})`; },
        available(id) { return names.has(Number(id)); },
        add(id) {
            id = Number(id);
            if (names.has(id) && !this.has(id)) this.selected.push(id);
        },
        remove(id) { this.selected = this.selected.filter((value) => value !== Number(id)); },
        move(id, direction) {
            const index = this.selected.indexOf(Number(id));
            const next = index + direction;
            if (![-1, 1].includes(direction) || index < 0 || next < 0 || next >= this.selected.length) return;
            [this.selected[index], this.selected[next]] = [this.selected[next], this.selected[index]];
        },
    };
}
