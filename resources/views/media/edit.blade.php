@extends('layouts.app')

@section('title', 'Editar Multimedia')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('media.index') }}" class="hover:text-primary transition-colors">Biblioteca Multimedia</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">Editar Multimedia</span>
        </div>
        <h1 class="text-2xl font-bold text-text">Editar Multimedia</h1>
        <p class="text-sm text-text-light mt-1">Actualiza el nombre o reemplaza el archivo de {{ $media->name }}</p>
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

        <form action="{{ route('media.update', $media) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Current File Info -->
            <div class="p-4 rounded-lg bg-surface border border-border">
                <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-2">Archivo Actual</p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text">{{ $media->original_name ?? $media->name }}</p>
                        <p class="text-xs text-text-light">{{ $media->size ? number_format($media->size / 1024 / 1024, 2) . ' MB' : '-' }} · {{ $media->mime_type ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-text mb-2">Nombre</label>
                <input type="text" id="name" name="name" value="{{ old('name', $media->name) }}"
                       class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
            </div>

            <!-- Replace File -->
            <div>
                <label class="block text-sm font-medium text-text mb-2">Reemplazar Archivo</label>
                <div class="border-2 border-dashed border-border rounded-xl p-6 text-center bg-surface hover:bg-primary/5 transition-colors">
                    <input type="file" name="file" id="file" accept="video/*,image/*"
                           class="hidden"
                           x-ref="replaceInput"
                           @change="$refs.replaceLabel.textContent = $event.target.files[0].name">
                    <div class="space-y-2">
                        <svg class="w-8 h-8 text-primary mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-sm text-text-light" x-ref="replaceLabel">Selecciona un nuevo archivo para reemplazar el actual</p>
                        <button type="button" @click="$refs.replaceInput.click()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-border text-sm font-medium text-text hover:bg-white transition-colors">
                            Seleccionar archivo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-border">
                <a href="{{ route('media.index') }}" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
