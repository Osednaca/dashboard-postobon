@php
    $editing = isset($establishment);
    $latitude = old('latitude', $establishment->latitude ?? '');
    $longitude = old('longitude', $establishment->longitude ?? '');
@endphp

@if($errors->any())
    <div class="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
        <p class="font-semibold">Revisa los datos marcados:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="mb-2 block text-sm font-medium text-text">Nombre <span class="text-danger">*</span></label>
        <input id="name" name="name" value="{{ old('name', $establishment->name ?? '') }}" required maxlength="255" autocomplete="organization" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
    </div>
    <div class="sm:col-span-2">
        <label for="business_type_id" class="mb-2 block text-sm font-medium text-text">Tipo de negocio <span class="text-danger">*</span></label>
        <div class="flex flex-col gap-2 sm:flex-row">
            <select id="business_type_id" name="business_type_id" required class="min-w-0 flex-1 rounded-lg border border-border bg-white px-4 py-2.5 text-base outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
                <option value="">Seleccionar tipo</option>
                @foreach($businessTypes as $businessType)<option value="{{ $businessType->id }}" @selected((string) old('business_type_id', $establishment->business_type_id ?? '') === (string) $businessType->id)>{{ $businessType->name }}</option>@endforeach
            </select>
            <a href="{{ route('business-types.index') }}" class="inline-flex items-center justify-center rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-text transition hover:bg-surface">Administrar tipos</a>
        </div>
    </div>
    <div>
        <label for="contact_name" class="mb-2 block text-sm font-medium text-text">Nombre de contacto</label>
        <input id="contact_name" name="contact_name" value="{{ old('contact_name', $establishment->contact_name ?? '') }}" maxlength="255" autocomplete="name" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
    </div>
    <div>
        <label for="contact_phone" class="mb-2 block text-sm font-medium text-text">Teléfono de contacto</label>
        <input id="contact_phone" name="contact_phone" type="tel" value="{{ old('contact_phone', $establishment->contact_phone ?? '') }}" maxlength="50" autocomplete="tel" placeholder="+57 300 000 0000" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
    </div>

    <div class="sm:col-span-2">
        <label for="address" class="mb-2 block text-sm font-medium text-text">Dirección <span class="text-danger">*</span></label>
        <input id="address" name="address" value="{{ old('address', $establishment->address ?? '') }}" required maxlength="500" autocomplete="street-address" placeholder="Busca una dirección o ubícala en el mapa" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
        <p class="mt-1.5 text-xs leading-5 text-text-muted">Selecciona una sugerencia o arrastra el marcador para guardar las coordenadas exactas.</p>
        @if(config('services.google_maps.key'))
            <div id="establishment-map" class="mt-3 h-72 w-full rounded-xl border border-border" aria-label="Mapa del establecimiento"></div>
            <input type="hidden" id="latitude" name="latitude" value="{{ $latitude }}">
            <input type="hidden" id="longitude" name="longitude" value="{{ $longitude }}">
        @else
            <div class="mt-3 rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-amber-900">Configura <code>GOOGLE_MAPS_API_KEY</code> para habilitar búsqueda y marcador. Mientras tanto puedes registrar las coordenadas manualmente.</div>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div><label for="latitude" class="mb-1.5 block text-sm font-medium text-text">Latitud</label><input id="latitude" name="latitude" type="number" step="0.00000001" min="-90" max="90" value="{{ $latitude }}" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"></div>
                <div><label for="longitude" class="mb-1.5 block text-sm font-medium text-text">Longitud</label><input id="longitude" name="longitude" type="number" step="0.00000001" min="-180" max="180" value="{{ $longitude }}" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"></div>
            </div>
        @endif
    </div>

    <div class="sm:col-span-2 border-t border-border pt-6">
        <h2 class="text-base font-semibold text-text">Red Wi‑Fi del establecimiento</h2>
        <p class="mt-1 text-sm text-text-light">La contraseña se cifra antes de guardarse y nunca se muestra en los listados.</p>
    </div>
    <div>
        <label for="wifi_ssid" class="mb-2 block text-sm font-medium text-text">ID Wi‑Fi (SSID)</label>
        <input id="wifi_ssid" name="wifi_ssid" value="{{ old('wifi_ssid', $establishment->wifi_ssid ?? '') }}" maxlength="255" autocomplete="off" spellcheck="false" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
    </div>
    <div x-data="{ visible: false }">
        <label for="wifi_password" class="mb-2 block text-sm font-medium text-text">Password Wi‑Fi</label>
        <div class="relative">
            <input id="wifi_password" name="wifi_password" :type="visible ? 'text' : 'password'" maxlength="255" autocomplete="new-password" placeholder="{{ $editing ? 'Dejar vacío para conservar' : 'Contraseña de la red' }}" class="w-full rounded-lg border border-border px-4 py-2.5 pr-20 text-base outline-none transition placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
            <button type="button" @click="visible = !visible" class="absolute inset-y-1 right-1 rounded-md px-3 text-xs font-semibold text-text-light transition hover:bg-surface" x-text="visible ? 'Ocultar' : 'Mostrar'" :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"></button>
        </div>
    </div>
</div>

@push('scripts')
@if(config('services.google_maps.key'))
<script>
    function initEstablishmentMap() {
        const latitude = document.getElementById('latitude');
        const longitude = document.getElementById('longitude');
        const address = document.getElementById('address');
        const initial = {
            lat: Number.parseFloat(latitude.value) || 4.7110,
            lng: Number.parseFloat(longitude.value) || -74.0721,
        };
        const map = new google.maps.Map(document.getElementById('establishment-map'), { center: initial, zoom: 15 });
        const marker = new google.maps.Marker({ position: initial, map, draggable: true });
        const autocomplete = new google.maps.places.Autocomplete(address);
        const geocoder = new google.maps.Geocoder();

        autocomplete.bindTo('bounds', map);
        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (!place.geometry?.location) return;
            const position = place.geometry.location;
            map.setCenter(position);
            marker.setPosition(position);
            latitude.value = position.lat();
            longitude.value = position.lng();
        });

        marker.addListener('dragend', ({ latLng }) => {
            latitude.value = latLng.lat();
            longitude.value = latLng.lng();
            geocoder.geocode({ location: latLng }, (results, status) => {
                if (status === 'OK' && results[0]) address.value = results[0].formatted_address;
            });
        });
    }
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&callback=initEstablishmentMap"></script>
@endif
@endpush
