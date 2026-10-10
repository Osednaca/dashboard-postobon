@props(['deviceKey' => null, 'title' => 'Contenido en los ventiladores'])

<section
    x-data="devicePreviews(@js(route('devices.previews')), @js($deviceKey))"
    class="device-previews rounded-xl border border-border bg-white p-5 shadow-sm sm:p-6"
    aria-label="Vista previa del contenido"
>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-text">{{ $title }}</h2>
            <p class="mt-1 text-sm text-text-light">Vista previa del archivo en reproducción.</p>
        </div>
        <button type="button" @click="refresh()" :disabled="updating" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/5 disabled:opacity-50">
            Actualizar
        </button>
    </div>
    <p x-show="loading" class="py-8 text-center text-sm text-text-muted" role="status">Consultando el contenido actual…</p>
    <p x-show="failed" x-cloak class="mb-4 rounded-lg bg-warning/10 px-3 py-2 text-sm text-amber-900" role="status">
        No se pudo actualizar el contenido. Reintentaremos automáticamente.
    </p>
    <p x-show="!loading && !failed && devices.length === 0" x-cloak class="rounded-lg bg-surface p-6 text-center text-sm text-text-muted">
        No hay contenido reportado para mostrar.
    </p>
    <div class="grid gap-4 {{ $deviceKey ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3' }}" x-cloak>
        <template x-for="device in devices" :key="device.key">
            <article x-data="previewPlayer" class="overflow-hidden rounded-xl border border-border bg-surface">
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-text" x-text="device.name"></h3>
                        <p class="mt-0.5 text-xs text-text-muted" x-text="device.type.toUpperCase()"></p>
                    </div>
                    <span class="h-2 w-2 shrink-0 rounded-full" :class="device.status === 'ready' && !error ? 'bg-success' : 'bg-text-muted'" aria-hidden="true"></span>
                </div>
                <div class="relative aspect-square w-full bg-black {{ $deviceKey ? 'max-h-[28rem]' : '' }}">
                    <video x-ref="video" x-show="device.status === 'ready' && !error" x-on:error="failed()"
                        autoplay muted loop playsinline controls preload="metadata" :aria-label="'Vista previa de ' + device.name"
                        class="h-full w-full object-contain"></video>
                    <div x-show="device.status !== 'ready' || error" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-50 p-6 text-center">
                        <svg class="h-9 w-9 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm text-text-muted" x-text="error ? 'No fue posible abrir el archivo de video.' : (labels[device.status] || labels.unavailable)"></p>
                        <button x-show="error" type="button" @click="retry()" class="text-xs font-semibold text-primary hover:underline">Reintentar</button>
                    </div>
                </div>
                <div class="flex items-start justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-text" x-text="device.media_name || (device.current_video ? (device.type === 'wl35' ? 'Video ' : '') + device.current_video : 'Sin video identificado')"></p>
                        <p class="mt-1 text-xs text-text-muted" x-text="error ? 'Archivo no disponible' : (labels[device.status] || labels.unavailable)"></p>
                    </div>
                    @unless($deviceKey)
                        <a x-show="device.detail_url" :href="device.detail_url" class="shrink-0 text-xs font-semibold text-primary hover:underline">Ver dispositivo</a>
                    @endunless
                </div>
            </article>
        </template>
    </div>
</section>
