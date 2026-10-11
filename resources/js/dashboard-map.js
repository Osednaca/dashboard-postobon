const labels = { online: 'En línea', offline: 'Sin conexión', unknown: 'Sin confirmar' };

export function validCoordinates(place) {
    return typeof place.latitude === 'number' && Number.isFinite(place.latitude)
        && typeof place.longitude === 'number' && Number.isFinite(place.longitude)
        && Math.abs(place.latitude) <= 90 && Math.abs(place.longitude) <= 180;
}

export function establishmentColor(devices) {
    if (devices.some(device => device.status === 'online')) return '#10B981';
    if (devices.some(device => device.status !== 'offline')) return '#F59E0B';
    return '#64748B';
}

export function createEstablishmentPopup(place, document) {
    const popup = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = place.name;
    popup.append(heading);
    const address = document.createElement('p');
    address.textContent = place.address || 'Sin dirección registrada';
    popup.append(address);
    const devices = place.devices || [];
    if (!devices.length) {
        const empty = document.createElement('p');
        empty.textContent = 'Sin dispositivos asignados';
        popup.append(empty);
        return popup;
    }
    const list = document.createElement('ul');
    for (const device of devices) {
        const item = document.createElement('li');
        let name = document.createElement('span');
        if (device.detail_url) {
            try {
                const url = new URL(device.detail_url, document.baseURI);
                if (url.origin === new URL(document.baseURI).origin && ['http:', 'https:'].includes(url.protocol)) {
                    name = document.createElement('a');
                    name.href = url.href;
                }
            } catch { /* Keep an unlinked name when the address is invalid. */ }
        }
        name.textContent = device.name;
        const status = document.createElement('span');
        status.textContent = ` · ${device.type.toUpperCase()} · ${labels[device.status] || labels.unknown}`;
        item.append(name, status);
        list.append(item);
    }
    popup.append(list);
    return popup;
}

export function mountEstablishmentMap(container, places, leaflet, document) {
    const map = leaflet.map(container).setView([4.7110, -74.0721], 5);
    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);
    const bounds = [];
    for (const place of places.filter(validCoordinates)) {
        const coordinates = [place.latitude, place.longitude];
        const color = establishmentColor(place.devices || []);
        leaflet.circleMarker(coordinates, {
            radius: 9, color, fillColor: color, fillOpacity: 0.85, weight: 2,
        }).addTo(map).bindPopup(createEstablishmentPopup(place, document));
        bounds.push(coordinates);
    }
    if (bounds.length) map.fitBounds(bounds, { padding: [32, 32], maxZoom: 14 });
    return map;
}

export function setupDashboardMap(document = window.document, leaflet = window.L) {
    const container = document.getElementById('device-map');
    const payload = document.getElementById('dashboard-map-data');
    if (!container || !payload) return;
    const message = document.getElementById('dashboard-map-message');
    try {
        if (!leaflet) throw new Error('Map unavailable');
        mountEstablishmentMap(container, JSON.parse(payload.textContent), leaflet, document);
    } catch {
        if (message) {
            message.hidden = false;
            message.textContent = 'No se pudo cargar el mapa. Recarga la página para volver a intentarlo.';
        }
    }
}
