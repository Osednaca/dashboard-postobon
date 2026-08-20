@extends('layouts.app')

@section('title', 'Nuevo Dispositivo')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('devices.index') }}" class="hover:text-primary transition-colors">Dispositivos</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">Nuevo Dispositivo</span>
        </div>
        <h1 class="text-2xl font-bold text-text">Nuevo Dispositivo</h1>
        <p class="text-sm text-text-light mt-1">Registra un nuevo dispositivo 3D Fan</p>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 sm:p-8">
        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-danger/10 border border-danger/20 text-danger text-sm">
                <div class="flex items-center gap-2 mb-2 font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Por favor corrige los siguientes errores:
                </div>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('devices.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Name -->
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-text mb-2">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- MAC Address -->
                <div>
                    <label for="mac_address" class="block text-sm font-medium text-text mb-2">Dirección MAC <span class="text-danger">*</span></label>
                    <input type="text" id="mac_address" name="mac_address" value="{{ old('mac_address') }}" required
                           placeholder="00:1A:2B:3C:4D:5E"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all font-mono">
                </div>

                <!-- RPM -->
                <div>
                    <label for="rpm" class="block text-sm font-medium text-text mb-2">RPM</label>
                    <input type="number" id="rpm" name="rpm" value="{{ old('rpm') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Firmware -->
                <div>
                    <label for="firmware" class="block text-sm font-medium text-text mb-2">Firmware</label>
                    <input type="text" id="firmware" name="firmware" value="{{ old('firmware') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Hardware -->
                <div>
                    <label for="hardware" class="block text-sm font-medium text-text mb-2">Hardware</label>
                    <input type="text" id="hardware" name="hardware" value="{{ old('hardware') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Establecimiento -->
                <div>
                    <label for="establishment" class="block text-sm font-medium text-text mb-2">Establecimiento</label>
                    <input type="text" id="establishment" name="establishment" value="{{ old('establishment') }}"
                           placeholder="Nombre del establecimiento"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Contacto -->
                <div>
                    <label for="contact_name" class="block text-sm font-medium text-text mb-2">Contacto (nombre)</label>
                    <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}"
                           placeholder="Persona de contacto"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Teléfono de contacto -->
                <div>
                    <label for="contact_phone" class="block text-sm font-medium text-text mb-2">Teléfono de contacto</label>
                    <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}"
                           placeholder="+57 300 000 0000"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Dirección con Google Autocomplete -->
                <div class="sm:col-span-2">
                    <label for="address" class="block text-sm font-medium text-text mb-2">Dirección</label>
                    <input type="text" id="address" name="address" value="{{ old('address') }}"
                           placeholder="Busca la dirección en el mapa..."
                           autocomplete="off"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                    <p class="mt-1.5 text-xs text-text-muted">Escribe la dirección y selecciona una sugerencia del mapa. Se completarán ciudad, país y coordenadas automáticamente.</p>

                    @if(config('services.google_maps.key'))
                        <div id="device-map" class="mt-3 h-64 w-full rounded-lg border border-border"></div>
                    @else
                        <div class="mt-3 p-3 rounded-lg bg-surface border border-border text-xs text-text-muted">
                            No se ha configurado <code>GOOGLE_MAPS_API_KEY</code>. Ingresa manualmente latitud y longitud si deseas ubicar el dispositivo en el mapa.
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-4">
                            <div>
                                <label for="latitude" class="block text-xs font-medium text-text mb-1">Latitud</label>
                                <input type="number" step="any" id="latitude" name="latitude" value="{{ old('latitude') }}"
                                       class="w-full px-3 py-2 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                            </div>
                            <div>
                                <label for="longitude" class="block text-xs font-medium text-text mb-1">Longitud</label>
                                <input type="number" step="any" id="longitude" name="longitude" value="{{ old('longitude') }}"
                                       class="w-full px-3 py-2 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                            </div>
                        </div>
                    @endif

                    <input type="hidden" id="city" name="city" value="{{ old('city') }}">
                    <input type="hidden" id="country" name="country" value="{{ old('country') }}">
                    @if(config('services.google_maps.key'))
                        <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
                        <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
                    @endif
                </div>

                <!-- Group -->
                <div>
                    <label for="group_id" class="block text-sm font-medium text-text mb-2">Grupo</label>
                    <select id="group_id" name="group_id"
                            class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                        <option value="">Seleccionar grupo</option>
                        @foreach(App\Models\Group::all() as $group)
                            <option value="{{ $group->id }}" {{ old('group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-border">
                <a href="{{ route('devices.index') }}" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-primary/90 transition-colors shadow-sm shadow-primary/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar Dispositivo
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@if(config('services.google_maps.key'))
<script>
    let deviceMap, deviceMarker;

    function initDeviceMap() {
        const lat = parseFloat(document.getElementById('latitude').value) || 4.7110;
        const lng = parseFloat(document.getElementById('longitude').value) || -74.0721;
        const center = { lat, lng };

        deviceMap = new google.maps.Map(document.getElementById('device-map'), {
            center,
            zoom: 14,
        });

        deviceMarker = new google.maps.Marker({
            position: center,
            map: deviceMap,
            draggable: true,
        });

        const input = document.getElementById('address');
        const autocomplete = new google.maps.places.Autocomplete(input);
        autocomplete.bindTo('bounds', deviceMap);

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                return;
            }

            deviceMap.setCenter(place.geometry.location);
            deviceMarker.setPosition(place.geometry.location);
            updateCoordinates(place.geometry.location.lat(), place.geometry.location.lng(), place);
        });

        deviceMarker.addListener('dragend', (e) => {
            updateCoordinates(e.latLng.lat(), e.latLng.lng(), null);
            reverseGeocode(e.latLng.lat(), e.latLng.lng());
        });
    }

    function updateCoordinates(lat, lng, place) {
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;

        if (place && place.address_components) {
            let city = '';
            let country = '';

            place.address_components.forEach((c) => {
                if (c.types.includes('locality') || c.types.includes('administrative_area_level_1')) {
                    city = c.long_name;
                }
                if (c.types.includes('country')) {
                    country = c.long_name;
                }
            });

            document.getElementById('city').value = city;
            document.getElementById('country').value = country;
        }
    }

    function reverseGeocode(lat, lng) {
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: { lat, lng } }, (results, status) => {
            if (status === 'OK' && results[0]) {
                document.getElementById('address').value = results[0].formatted_address;
                updateCoordinates(lat, lng, results[0]);
            }
        });
    }
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&callback=initDeviceMap"></script>
@endif
@endpush
