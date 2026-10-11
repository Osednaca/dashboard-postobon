<x-app-layout>
    <x-slot name="title">Nueva Campaña</x-slot>

    @php
        $allMedia = \App\Models\Media::all();
    @endphp

    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('campaigns.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Nueva Campaña</h2>
                <p class="mt-1 text-sm text-text-muted">Crea una nueva campaña publicitaria</p>
            </div>
        </div>

        <form action="{{ route('campaigns.store') }}" method="POST" class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]" x-data="{ currentTab: @js($errors->has('media_ids') || $errors->has('media_ids.*') ? 'videos' : ($errors->has('target_devices') || $errors->has('target_devices.*') ? 'segmentation' : 'general')) }">
            @csrf

            {{-- Tabs --}}
            <div class="border-b border-border">
                <nav class="flex -mb-px overflow-x-auto px-6 pt-4 [&>button]:shrink-0">
                    <button type="button" @click="currentTab = 'general'" :class="currentTab === 'general' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        Información General
                    </button>
                    <button type="button" @click="currentTab = 'videos'" :class="currentTab === 'videos' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        Videos
                    </button>
                    <button type="button" @click="currentTab = 'segmentation'" :class="currentTab === 'segmentation' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        Segmentación
                    </button>
                </nav>
            </div>

            <div class="p-6 space-y-6">
                {{-- General Tab --}}
                <div x-show="currentTab === 'general'" x-transition>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-text mb-1.5">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" required value="{{ old('name') }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors" placeholder="Ej. Campaña Verano 2024">
                            @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-text mb-1.5">Descripción</label>
                            <textarea name="description" id="description" rows="4" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors resize-none" placeholder="Describe los objetivos y detalles de la campaña...">{{ old('description') }}</textarea>
                            @error('description')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            <div>
                                <label for="priority" class="block text-sm font-medium text-text mb-1.5">Prioridad (1-10)</label>
                                <input type="number" name="priority" id="priority" min="1" max="10" value="{{ old('priority', 5) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                @error('priority')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-text mb-1.5">Fecha de inicio <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="start_date" required value="{{ old('start_date') }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                @error('start_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <x-campaign-end-date />
                        </div>
                    </div>
                </div>

                {{-- Videos Tab --}}
                <div x-show="currentTab === 'videos'" x-transition>
                    <x-campaign-media-selector :media="$allMedia" />
                </div>

                {{-- Segmentation Tab --}}
                <div x-show="currentTab === 'segmentation'" x-transition>
                    <x-campaign-target-selector />
                </div>
            </div>

            <div class="px-6 py-4 bg-surface border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('campaigns.index') }}" class="px-4 py-2.5 rounded-lg text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">Crear Campaña</button>
            </div>
        </form>
    </div>
</x-app-layout>
