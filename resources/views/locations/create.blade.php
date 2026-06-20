@extends('layouts.app')

@section('title', 'Nueva Ubicación')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('locations.index') }}" class="hover:text-primary transition-colors">Ubicaciones</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">Nueva Ubicación</span>
        </div>
        <h1 class="text-2xl font-bold text-text">Nueva Ubicación</h1>
        <p class="text-sm text-text-light mt-1">Registra una nueva ubicación para tus dispositivos</p>
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

        <form action="{{ route('locations.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Name -->
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-text mb-2">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Address -->
                <div class="sm:col-span-2">
                    <label for="address" class="block text-sm font-medium text-text mb-2">Dirección</label>
                    <input type="text" id="address" name="address" value="{{ old('address') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- City -->
                <div>
                    <label for="city" class="block text-sm font-medium text-text mb-2">Ciudad</label>
                    <input type="text" id="city" name="city" value="{{ old('city') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Country -->
                <div>
                    <label for="country" class="block text-sm font-medium text-text mb-2">País</label>
                    <input type="text" id="country" name="country" value="{{ old('country') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Latitude -->
                <div>
                    <label for="latitude" class="block text-sm font-medium text-text mb-2">Latitud</label>
                    <input type="number" step="any" id="latitude" name="latitude" value="{{ old('latitude') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Longitude -->
                <div>
                    <label for="longitude" class="block text-sm font-medium text-text mb-2">Longitud</label>
                    <input type="number" step="any" id="longitude" name="longitude" value="{{ old('longitude') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Contact Name -->
                <div>
                    <label for="contact_name" class="block text-sm font-medium text-text mb-2">Contacto</label>
                    <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-text mb-2">Teléfono</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-border">
                <a href="{{ route('locations.index') }}" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar Ubicación
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
