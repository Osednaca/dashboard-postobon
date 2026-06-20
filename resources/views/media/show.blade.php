@extends('layouts.app')

@section('title', $media->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-text-light mb-2">
                <a href="{{ route('media.index') }}" class="hover:text-primary transition-colors">Biblioteca Multimedia</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-text">{{ $media->name }}</span>
            </div>
            <h1 class="text-2xl font-bold text-text">{{ $media->name }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('media.edit', $media) }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
            </a>
            <form action="{{ route('media.destroy', $media) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este archivo?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-danger text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    @php
        $mediaUrl = function($item) {
            if (str_starts_with($item->file_path, 'http')) {
                return $item->file_path;
            }
            return asset('storage/' . $item->file_path);
        };
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Video Preview -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Vista Previa
                </h2>
                <div class="aspect-video bg-black rounded-lg overflow-hidden">
                    @if($media->mime_type && str_starts_with($media->mime_type, 'video/'))
                        <video controls class="w-full h-full">
                            <source src="{{ $mediaUrl($media) }}" type="{{ $media->mime_type }}">
                            Tu navegador no soporta la reproducción de video.
                        </video>
                    @elseif($media->mime_type && str_starts_with($media->mime_type, 'image/'))
                        <img src="{{ $mediaUrl($media) }}" alt="{{ $media->name }}" class="w-full h-full object-contain">
                    @else
                        <div class="w-full h-full flex flex-col items-center justify-center text-white">
                            <svg class="w-12 h-12 mb-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm opacity-80">Vista previa no disponible</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 sticky top-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Detalles
                </h2>
                <div class="space-y-4">
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Nombre</p>
                        <p class="text-sm text-text font-medium">{{ $media->name }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Tamaño</p>
                        <p class="text-sm text-text">{{ $media->size ? number_format($media->size / 1024 / 1024, 2) . ' MB' : '-' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Duración</p>
                        <p class="text-sm text-text">{{ $media->duration ? gmdate('i:s', $media->duration) : '-' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Tipo MIME</p>
                        <p class="text-sm text-text font-mono">{{ $media->mime_type ?? '-' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Fecha de Subida</p>
                        <p class="text-sm text-text">{{ $media->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Última Actualización</p>
                        <p class="text-sm text-text">{{ $media->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaigns Using This Media -->
    <div class="mt-6 bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
        <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
            Campañas que usan este archivo
        </h2>
        @if($media->campaigns->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="px-4 py-3 font-semibold text-text">Campaña</th>
                            <th class="px-4 py-3 font-semibold text-text">Estado</th>
                            <th class="px-4 py-3 font-semibold text-text">Orden</th>
                            <th class="px-4 py-3 font-semibold text-text text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($media->campaigns as $campaign)
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-4 py-3 font-medium text-text">{{ $campaign->name }}</td>
                                <td class="px-4 py-3">
                                    <x-campaign-status-badge :status="$campaign->status" />
                                </td>
                                <td class="px-4 py-3 text-text-light">#{{ $campaign->pivot->order ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('campaigns.show', $campaign) }}" class="inline-flex items-center gap-1 text-sm text-primary hover:text-red-700 font-medium transition-colors">
                                        Ver
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <div class="w-12 h-12 rounded-xl bg-surface flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                </div>
                <p class="text-sm text-text-light">Este archivo no está siendo utilizado en ninguna campaña</p>
            </div>
        @endif
    </div>
</div>
@endsection
