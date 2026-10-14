@extends('layouts.app')

@section('title', 'Nuevo Grupo')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <a href="{{ route('groups.index') }}" class="text-sm font-medium text-primary hover:underline">← Grupos</a>
        <h1 class="mt-3 text-2xl font-bold text-text">Nuevo Grupo</h1>
        <p class="mt-1 text-sm text-text-light">Organiza equipos existentes, por ejemplo en un grupo llamado Medellín.</p>
    </div>
    <form action="{{ route('groups.store') }}" method="POST" class="space-y-6">
        @csrf
        @if($errors->any())
            <div class="rounded-lg border border-danger/20 bg-danger/10 p-4 text-sm text-danger" role="alert">
                <p class="font-semibold">Revisa los siguientes datos:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <section class="space-y-5 rounded-xl border border-border bg-white p-6">
            <h2 class="text-lg font-semibold text-text">Datos del grupo</h2>
            <div>
                <label for="name" class="mb-2 block text-sm font-medium text-text">Nombre <span class="text-danger">*</span></label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Por ejemplo, Medellín" class="w-full rounded-lg border border-border px-4 py-2.5 text-sm focus:border-primary focus:ring-primary/20">
            </div>
            <div>
                <label for="description" class="mb-2 block text-sm font-medium text-text">Descripción</label>
                <textarea id="description" name="description" rows="3" class="w-full rounded-lg border border-border px-4 py-2.5 text-sm focus:border-primary focus:ring-primary/20">{{ old('description') }}</textarea>
            </div>
            <div>
                <label for="business_type_id" class="mb-2 block text-sm font-medium text-text">Tipo de negocio <span class="font-normal text-text-muted">(opcional)</span></label>
                <select id="business_type_id" name="business_type_id" class="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm">
                    <option value="">Sin clasificación</option>
                    @foreach($businessTypes as $businessType)
                        <option value="{{ $businessType->id }}" @selected((string) old('business_type_id') === (string) $businessType->id)>{{ $businessType->name }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs leading-5 text-text-muted">Describe el grupo. La segmentación de campañas usa el tipo de negocio del establecimiento de cada equipo.</p>
            </div>
        </section>
        <section class="space-y-5 rounded-xl border border-border bg-white p-6">
            <h2 class="text-lg font-semibold text-text">Ubicación <span class="text-sm font-normal text-text-muted">(opcional)</span></h2>
            <div>
                <label for="address" class="mb-2 block text-sm font-medium text-text">Dirección o lugar</label>
                <input id="address" name="address" value="{{ old('address') }}" maxlength="500" autocomplete="street-address" placeholder="Busca una dirección o ciudad" class="w-full rounded-lg border border-border px-4 py-2.5 text-sm">
            </div>
            <div>
                <label for="city" class="mb-2 block text-sm font-medium text-text">Ciudad</label>
                <input id="city" name="city" value="{{ old('city') }}" maxlength="255" autocomplete="address-level2" class="w-full rounded-lg border border-border px-4 py-2.5 text-sm">
            </div>
            @if(config('services.google_maps.key'))
                <p class="text-xs text-text-muted">Selecciona una sugerencia, haz clic en el mapa o arrastra el marcador. También puedes introducir las coordenadas.</p>
                <div id="group-map" class="h-72 rounded-xl border border-border" aria-label="Mapa del grupo"></div>
                <p id="group-map-status" class="text-xs text-text-muted" role="status">Cargando mapa… Si no está disponible, puedes completar la ubicación manualmente.</p>
            @else
                <p class="rounded-lg bg-surface p-3 text-sm text-text-light">La búsqueda en el mapa no está disponible. Puedes guardar la dirección, ciudad y coordenadas manualmente.</p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="latitude" class="mb-2 block text-sm font-medium text-text">Latitud</label><input id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude') }}" class="w-full rounded-lg border border-border px-4 py-2.5 text-sm"></div>
                <div><label for="longitude" class="mb-2 block text-sm font-medium text-text">Longitud</label><input id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude') }}" class="w-full rounded-lg border border-border px-4 py-2.5 text-sm"></div>
            </div>
            <input id="google_place_id" name="google_place_id" type="hidden" value="{{ old('google_place_id') }}">
        </section>
        <section class="space-y-4 rounded-xl border border-border bg-white p-6" x-data="groupDeviceSelector({ devices: @js($groupDevices), selected: @js(old('device_keys', [])) })">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div><h2 class="text-lg font-semibold text-text">Dispositivos del grupo</h2><p class="mt-1 text-sm text-text-light">Selecciona los equipos existentes que pertenecerán a este grupo.</p></div>
                <span class="rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary" x-text="selected.length + ' seleccionados'" aria-live="polite"></span>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div><label for="device-search" class="mb-1 block text-xs font-medium text-text-light">Buscar equipo o establecimiento</label><input id="device-search" x-model="search" type="search" placeholder="Nombre, establecimiento o grupo" class="w-full rounded-lg border border-border px-3 py-2 text-sm"></div>
                <div><label for="device-model" class="mb-1 block text-xs font-medium text-text-light">Modelo</label><select id="device-model" x-model="model" class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm"><option value="">Todos</option><option value="z2">42</option><option value="wl35">35</option></select></div>
                <div><label for="device-business-type" class="mb-1 block text-xs font-medium text-text-light">Tipo de negocio del establecimiento</label><select id="device-business-type" x-model="businessType" class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm"><option value="">Todos</option>@foreach($businessTypes as $businessType)<option value="{{ $businessType->id }}">{{ $businessType->name }}</option>@endforeach</select></div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm font-medium"><button type="button" @click="selectVisible()" class="text-primary hover:underline">Seleccionar resultados</button><button type="button" @click="clearSelection()" class="text-text-light hover:underline">Quitar selección</button></div>
            <div x-show="movedDevices.length" x-cloak class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-amber-900" role="status">
                <p><strong x-text="movedDevices.length"></strong> equipo(s) ya pertenecen a otro grupo. Al guardar se moverán a este grupo.</p>
                <ul class="mt-2 list-inside list-disc"><template x-for="device in movedDevices" :key="device.key"><li><span x-text="device.name"></span> — <span x-text="device.group_name || 'Otro grupo'"></span></li></template></ul>
            </div>
            <div class="max-h-96 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                <template x-for="device in filteredDevices" :key="device.key">
                    <label class="flex cursor-pointer items-start gap-3 p-4 hover:bg-surface">
                        <input type="checkbox" :value="device.key" x-model="selected" class="mt-1 rounded border-border text-primary">
                        <span class="min-w-0 flex-1"><span class="block font-medium text-text" x-text="device.name"></span><span class="block text-xs text-text-light" x-text="[device.establishment_name || 'Sin establecimiento', device.business_type_name, device.group_name ? 'Grupo actual: ' + device.group_name : 'Sin grupo'].filter(Boolean).join(' · ')"></span><span class="block break-all text-xs text-text-muted" x-text="device.id"></span></span>
                        <span class="rounded-full bg-surface px-2 py-1 text-xs font-medium" x-text="device.type === 'wl35' ? '35' : '42'"></span>
                    </label>
                </template>
                <p x-show="!filteredDevices.length" class="p-6 text-center text-sm text-text-muted">No hay dispositivos que coincidan con la búsqueda.</p>
            </div>
            <template x-for="key in selected" :key="key"><input type="hidden" name="device_keys[]" :value="key"></template>
            <noscript><p class="text-sm text-danger">Activa JavaScript para seleccionar dispositivos.</p></noscript>
        </section>
        <div class="flex justify-end gap-3">
            <a href="{{ route('groups.index') }}" class="rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-text">Cancelar</a>
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary/90">Guardar Grupo</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@if(config('services.google_maps.key'))
<script>
    function initGroupMap() {
        const latitude = document.getElementById('latitude');
        const longitude = document.getElementById('longitude');
        const address = document.getElementById('address');
        const city = document.getElementById('city');
        const placeId = document.getElementById('google_place_id');
        const coordinates = () => {
            const lat = Number.parseFloat(latitude.value);
            const lng = Number.parseFloat(longitude.value);
            return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? { lat, lng } : null;
        };
        const initial = coordinates();
        const map = new google.maps.Map(document.getElementById('group-map'), { center: initial || { lat: 4.7110, lng: -74.0721 }, zoom: initial ? 15 : 6 });
        const marker = new google.maps.Marker({ position: initial || map.getCenter(), map, draggable: true, visible: Boolean(initial) });
        const autocomplete = new google.maps.places.Autocomplete(address, { fields: ['geometry', 'formatted_address', 'address_components', 'place_id'] });
        const geocoder = new google.maps.Geocoder();
        let requestVersion = 0;
        const updatePlace = (place) => {
            address.value = place.formatted_address || address.value;
            placeId.value = place.place_id || '';
            city.value = (place.address_components || []).find(component => component.types.includes('locality') || component.types.includes('administrative_area_level_2'))?.long_name || '';
        };
        const updatePosition = (position) => {
            latitude.value = position.lat();
            longitude.value = position.lng();
            marker.setPosition(position);
            marker.setVisible(true);
        };
        autocomplete.bindTo('bounds', map);
        autocomplete.addListener('place_changed', () => {
            requestVersion++;
            const place = autocomplete.getPlace();
            if (!place.geometry?.location) return;
            updatePlace(place);
            updatePosition(place.geometry.location);
            if (place.geometry.viewport) map.fitBounds(place.geometry.viewport);
            else { map.setCenter(place.geometry.location); map.setZoom(15); }
        });
        const moveMarker = ({ latLng }) => {
            const version = ++requestVersion;
            updatePosition(latLng);
            placeId.value = '';
            geocoder.geocode({ location: latLng }, (results, status) => {
                if (version === requestVersion && status === 'OK' && results[0]) updatePlace(results[0]);
            });
        };
        marker.addListener('dragend', moveMarker);
        map.addListener('click', moveMarker);
        [latitude, longitude].forEach(input => input.addEventListener('input', () => {
            requestVersion++;
            placeId.value = '';
            const position = coordinates();
            marker.setVisible(Boolean(position));
            if (position) { marker.setPosition(position); map.setCenter(position); }
        }));
        address.addEventListener('input', () => { requestVersion++; placeId.value = ''; });
        document.getElementById('group-map-status').textContent = 'Mapa disponible. La ubicación es opcional.';
    }
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&callback=initGroupMap"></script>
@endif
@endpush
