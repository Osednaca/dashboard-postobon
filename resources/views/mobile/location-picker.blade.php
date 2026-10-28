<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Ubicación del establecimiento</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px system-ui, sans-serif; color: #0F172A; background: #F8FAFC; }
        main { padding: 16px; display: flex; flex-direction: column; height: 100dvh; gap: 12px; }
        label { font-weight: 600; }
        input, button { font: inherit; padding: 14px; border-radius: 10px; }
        input { border: 1px solid #CBD5E1; width: 100%; background: white; }
        button { border: 0; color: white; background: #2D5795; font-weight: 600; }
        button:disabled { opacity: .45; }
        #map { flex: 1; min-height: 240px; border-radius: 12px; }
        #status { margin: 0; font-size: 14px; color: #475569; }
        #place-search { width: 100%; }
    </style>
</head>
<body>
<main>
    <div id="place-search" aria-label="Buscar dirección o establecimiento"></div>
    <label for="address">Dirección de la ubicación seleccionada</label>
    <input id="address" maxlength="500" value="{{ $address }}" autocomplete="off" placeholder="Dirección o nombre del lugar">
    <p id="status" role="status">Busca una dirección o toca el mapa para ubicar el establecimiento.</p>
    <div id="map" aria-label="Mapa de ubicación"></div>
    <button id="confirm" type="button" disabled>Usar ubicación</button>
</main>
<script>
    const nonce = {{ Illuminate\Support\Js::from($nonce) }};
    const initial = {{ Illuminate\Support\Js::from(['latitude' => $latitude, 'longitude' => $longitude]) }};
    const address = document.getElementById('address');
    const status = document.getElementById('status');
    const confirm = document.getElementById('confirm');
    let selected = null;
    let lookup = 0;
    let geocoding = false;
    let manualAddress = false;
    function post(value) {
        window.ReactNativeWebView?.postMessage(JSON.stringify({ ...value, nonce }));
    }
    function failure(message) {
        status.textContent = message;
        post({ type: 'location-error', message });
    }
    function refresh() { confirm.disabled = geocoding || !selected || !address.value.trim(); }
    address.addEventListener('input', () => {
        // Typed text alone is not a verified location. Choose a suggestion or tap the map.
        if (!manualAddress) selected = null;
        if (geocoding) { lookup++; geocoding = false; }
        refresh();
    });
    confirm.addEventListener('click', () => {
        if (!geocoding && selected && address.value.trim()) post({ type: 'location-selected', address: address.value.trim(), ...selected });
    });
    window.gm_authFailure = () => failure('Google Maps no está autorizado para este dominio. Revisa la configuración del servidor.');
    window.initLocationPicker = function () {
        const existing = initial.latitude !== null && initial.longitude !== null;
        const center = existing ? { lat: initial.latitude, lng: initial.longitude } : { lat: 4.5709, lng: -74.2973 };
        const map = new google.maps.Map(document.getElementById('map'), {
            center, zoom: existing ? 17 : 5, gestureHandling: 'greedy', fullscreenControl: false,
            streetViewControl: false, mapTypeControl: false,
        });
        const marker = new google.maps.Marker({ position: center, map: existing ? map : null, draggable: true });
        const geocoder = new google.maps.Geocoder();
        const autocomplete = new google.maps.places.PlaceAutocompleteElement();
        autocomplete.setAttribute('aria-label', 'Buscar dirección o establecimiento');
        document.getElementById('place-search').appendChild(autocomplete);
        if (existing) selected = { latitude: initial.latitude, longitude: initial.longitude };
        refresh();
        function choose(point, label) {
            const request = ++lookup;
            manualAddress = false;
            geocoding = false;
            selected = { latitude: point.lat(), longitude: point.lng() };
            marker.setMap(map);
            marker.setPosition(point);
            if (label) {
                address.value = label;
                status.textContent = 'Ubicación seleccionada. Puedes mover el marcador con el dedo.';
                refresh();
                return;
            }
            address.value = '';
            geocoding = true;
            status.textContent = 'Buscando la dirección de este punto…';
            refresh();
            geocoder.geocode({ location: point }).then(({ results }) => {
                if (request !== lookup) return;
                geocoding = false;
                manualAddress = !results[0]?.formatted_address;
                if (results[0]?.formatted_address) address.value = results[0].formatted_address;
                status.textContent = !manualAddress ? 'Ubicación seleccionada. Confirma la dirección.' : 'Punto seleccionado. Escribe la dirección exacta de este punto o elige una sugerencia.';
                refresh();
            }).catch(() => {
                if (request !== lookup) return;
                geocoding = false;
                manualAddress = true;
                status.textContent = 'Punto seleccionado. No se pudo consultar su dirección; escribe la dirección exacta de este punto o elige una sugerencia.';
                refresh();
            });
        }
        autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
            const request = ++lookup;
            selected = null;
            manualAddress = false;
            geocoding = true;
            address.value = '';
            status.textContent = 'Consultando la ubicación seleccionada…';
            refresh();
            try {
                const place = placePrediction.toPlace();
                await place.fetchFields({ fields: ['formattedAddress', 'location'] });
                if (request !== lookup) return;
                geocoding = false;
                if (!place.location) throw new Error('missing_location');
                map.panTo(place.location);
                map.setZoom(17);
                choose(place.location, place.formattedAddress);
            } catch {
                if (request !== lookup) return;
                geocoding = false;
                refresh();
                failure('No se pudo consultar ese lugar. Reintenta la búsqueda o ubícalo tocando el mapa.');
            }
        });
        autocomplete.addEventListener('gmp-error', () => failure('Google Places no está disponible. Revisa Places API (New), la clave y la facturación del proyecto.'));
        map.addListener('click', (event) => { if (event.latLng) choose(event.latLng); });
        marker.addListener('dragend', (event) => { if (event.latLng) choose(event.latLng); });
    };
</script>
@if($mapsKey)
    <script async src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&amp;libraries=places&amp;callback=initLocationPicker&amp;language=es" onerror="failure('No se pudo cargar Google Maps. Revisa tu conexión e inténtalo de nuevo.')"></script>
@else
    <script>failure('Google Maps no está configurado. Solicita al administrador configurar GOOGLE_MAPS_API_KEY en el servidor.');</script>
@endif
</body>
</html>
