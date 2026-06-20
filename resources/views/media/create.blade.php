@extends('layouts.app')

@section('title', 'Subir Video')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('media.index') }}" class="hover:text-primary transition-colors">Biblioteca Multimedia</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">Subir Video</span>
        </div>
        <h1 class="text-2xl font-bold text-text">Subir Video</h1>
        <p class="text-sm text-text-light mt-1">Sube un nuevo archivo multimedia a la biblioteca</p>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 sm:p-8"
         x-data="{ 
            file: null, 
            fileName: '', 
            progress: 0, 
            isUploading: false,
            dragOver: false,
            handleFile(file) {
                this.file = file;
                this.fileName = file.name;
            }
         }"
         @dragover.prevent="dragOver = true"
         @dragleave.prevent="dragOver = false"
         @drop.prevent="dragOver = false; handleFile($event.dataTransfer.files[0])">

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

        <form action="{{ route('media.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6" @submit="isUploading = true">
            @csrf

            <!-- File Upload -->
            <div>
                <label class="block text-sm font-medium text-text mb-2">Archivo <span class="text-danger">*</span></label>
                <div class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
                     :class="dragOver ? 'border-primary bg-primary/5' : (file ? 'border-success bg-success/5' : 'border-border bg-surface')">
                    <input type="file" name="file" id="file" required accept="video/*,image/*"
                           class="hidden"
                           @change="handleFile($event.target.files[0])"
                           x-ref="fileInput">
                    
                    <div x-show="!file" class="space-y-3">
                        <div class="w-14 h-14 rounded-2xl bg-white border border-border flex items-center justify-center mx-auto">
                            <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-text font-medium">Arrastra y suelta tu archivo aquí</p>
                            <p class="text-xs text-text-light mt-1">o</p>
                        </div>
                        <button type="button" @click="$refs.fileInput.click()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-border text-sm font-medium text-text hover:bg-white transition-colors">
                            Seleccionar archivo
                        </button>
                        <p class="text-xs text-text-muted">Soporta videos e imágenes (MP4, AVI, WEBM, JPG, PNG)</p>
                    </div>

                    <div x-show="file" class="space-y-3">
                        <div class="w-14 h-14 rounded-2xl bg-success/10 flex items-center justify-center mx-auto">
                            <svg class="w-7 h-7 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-text font-medium" x-text="fileName"></p>
                        <button type="button" @click="file = null; fileName = ''; $refs.fileInput.value = ''" class="text-xs text-danger hover:text-red-700 font-medium transition-colors">
                            Cambiar archivo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-text mb-2">Nombre</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                <p class="text-xs text-text-muted mt-1">Si se deja vacío, se usará el nombre original del archivo.</p>
            </div>

            <!-- Progress Bar -->
            <div x-show="isUploading" class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-text-light">Subiendo archivo...</span>
                    <span class="text-text font-medium" x-text="progress + '%'"></span>
                </div>
                <div class="w-full h-2 bg-surface rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-border">
                <a href="{{ route('media.index') }}" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</a>
                <button type="submit" :disabled="isUploading" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)] disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span x-text="isUploading ? 'Subiendo...' : 'Subir Archivo'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
