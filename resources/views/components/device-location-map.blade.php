@props(['profile' => null, 'deviceName' => 'Dispositivo'])

@php
    $establishment = $profile?->establishmentProfile;
    $coordinateSource = null;
    foreach ([$establishment, $profile, $profile?->location] as $candidate) {
        $latitude = $candidate?->latitude;
        $longitude = $candidate?->longitude;
        if (is_numeric($latitude) && is_numeric($longitude)
            && is_finite((float) $latitude) && is_finite((float) $longitude)
            && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180) {
            $coordinateSource = $candidate;
            break;
        }
    }
    $coordinates = $coordinateSource
        ? (float) $coordinateSource->latitude . ',' . (float) $coordinateSource->longitude
        : null;
    $mapsUrl = $coordinates !== null ? 'https://www.google.com/maps?q=' . $coordinates : null;
@endphp

<section class="relative isolate z-0 overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]" aria-label="Ubicación del dispositivo">
    <div class="border-b border-border px-5 py-4 sm:px-6">
        <h2 class="text-lg font-semibold text-text">Ubicación del dispositivo</h2>
        @if($coordinateSource?->address)
            <p class="mt-1 break-words text-sm leading-6 text-text-light">{{ $coordinateSource->address }}</p>
        @endif
    </div>
    @if($mapsUrl)
        <iframe title="Mapa de {{ $deviceName }}" src="{{ $mapsUrl . '&output=embed' }}" class="h-72 w-full border-0 sm:h-80" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-xs sm:px-6">
            <span class="font-mono tabular-nums text-text-muted">{{ $coordinates }}</span>
            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">Abrir en Google Maps</a>
        </div>
    @else
        <div class="px-5 py-8 text-center sm:px-6">
            <p class="text-sm font-semibold text-text">Sin coordenadas registradas</p>
            <p class="mt-1 text-sm text-text-light">Ubica el establecimiento en el mapa para mostrarlo aquí.</p>
            @if($establishment)
                <a href="{{ route('establishments.edit', $establishment->id) }}" class="mt-3 inline-flex text-sm font-semibold text-primary hover:underline">Editar ubicación del establecimiento</a>
            @else
                <p class="mt-2 text-xs text-text-muted">Selecciona un establecimiento en Información administrativa.</p>
            @endif
        </div>
    @endif
</section>
