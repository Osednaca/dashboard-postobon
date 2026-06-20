@props([
    'height' => '400',
    'locations' => [],
])

<div class="bg-white rounded-xl border border-border p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-semibold text-text">Mapa de dispositivos</h3>
        <span class="text-xs text-text-muted">{{ count($locations) }} ubicaciones</span>
    </div>
    <div id="map-{{ $attributes->get('id', uniqid()) }}" class="rounded-lg border border-border overflow-hidden" style="height: {{ $height }}px;">
        <div class="w-full h-full flex items-center justify-center bg-surface">
            <div class="text-center">
                <svg class="w-10 h-10 text-text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 7m0 13V7"></path>
                </svg>
                <p class="text-sm text-text-muted">Mapa de Leaflet.js</p>
            </div>
        </div>
    </div>
</div>
