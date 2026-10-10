@props(['media', 'detail' => false, 'compact' => false])
@php
    $kind = str_starts_with($media->mime_type, 'video/') ? 'video'
        : (in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'], true) ? 'image' : 'unsupported');
@endphp
<div
    x-data="mediaPreview(@js((string) $media->id), @js(route('media.content', $media)), @js($kind), @js($media->duration ?? 0))"
    {{ $attributes->class(['media-preview relative overflow-hidden bg-black', 'h-10 w-10 shrink-0 rounded-lg' => $compact, 'aspect-video w-full' => !$compact]) }}
    aria-label="Vista previa de {{ $media->name }}"
>
    @if($kind === 'video')
        <video x-ref="video" :src="source" preload="metadata" muted playsinline @if($detail) controls @endif
            class="h-full w-full object-contain" :class="status === 'ready' ? 'opacity-100' : 'opacity-0'"
            x-on:loadedmetadata="metadata($event.target)" x-on:loadeddata="loaded()" x-on:seeked="ready()" x-on:error="fail()">
            Tu navegador no soporta este video.
        </video>
    @elseif($kind === 'image')
        <img :src="source" alt="{{ $media->name }}" class="h-full w-full object-contain"
            x-on:load="ready()" x-on:error="fail()" :class="status === 'ready' ? 'opacity-100' : 'opacity-0'">
    @endif
    <div x-show="status !== 'ready'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-surface p-2 text-center text-xs text-text-light" role="status"
        :aria-label="status === 'error' ? 'No se pudo cargar la vista previa' : 'Cargando vista previa'">
        @if($compact)
            <span x-show="status !== 'error'">…</span>
        @else
            <span x-text="status === 'error' ? 'No se pudo cargar la vista previa.' : (status === 'unsupported' ? 'Vista previa no disponible.' : 'Cargando vista previa…')">Cargando vista previa…</span>
        @endif
        <button x-show="status === 'error'" x-cloak type="button" @click.stop="retry()" aria-label="Reintentar vista previa"
            class="rounded border border-border bg-white px-2 py-1 text-xs font-semibold text-primary">
            {{ $compact ? '↻' : 'Reintentar' }}
        </button>
    </div>
</div>
