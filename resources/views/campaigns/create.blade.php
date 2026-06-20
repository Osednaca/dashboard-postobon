<x-app-layout>
    <x-slot name="title">Nueva Campaña</x-slot>

    @php
        $allMedia = \App\Models\Media::all();
        $allLocations = \App\Models\Location::all();
        $allGroups = \App\Models\Group::all();
        $cities = $allLocations->pluck('city')->unique()->filter()->sort()->values()->toArray();
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

        <form action="{{ route('campaigns.store') }}" method="POST" class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]" x-data="{ currentTab: 'general' }">
            @csrf

            {{-- Tabs --}}
            <div class="border-b border-border">
                <nav class="flex -mb-px px-6 pt-4">
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
                            <input type="text" name="name" id="name" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors" placeholder="Ej. Campaña Verano 2024">
                            @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-text mb-1.5">Descripción</label>
                            <textarea name="description" id="description" rows="4" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors resize-none" placeholder="Describe los objetivos y detalles de la campaña..."></textarea>
                            @error('description')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            <div>
                                <label for="priority" class="block text-sm font-medium text-text mb-1.5">Prioridad (1-10)</label>
                                <input type="number" name="priority" id="priority" min="1" max="10" value="5" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                @error('priority')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-text mb-1.5">Fecha de inicio <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="start_date" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                @error('start_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-text mb-1.5">Fecha de fin <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="end_date" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                @error('end_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Videos Tab --}}
                <div x-show="currentTab === 'videos'" x-transition x-data="{ selectedVideos: [] }">
                    <div class="space-y-4">
                        <p class="text-sm text-text-muted">Selecciona los videos de la biblioteca de medios para esta campaña.</p>

                        @if($allMedia->count() > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($allMedia as $mediaItem)
                                    <div class="relative group rounded-lg border border-border hover:border-primary/50 transition-colors cursor-pointer"
                                         @click="if(selectedVideos.includes({{ $mediaItem->id }})) { selectedVideos = selectedVideos.filter(v => v !== {{ $mediaItem->id }}) } else { selectedVideos.push({{ $mediaItem->id }}) }"
                                         :class="selectedVideos.includes({{ $mediaItem->id }}) ? 'border-primary bg-primary/5' : 'bg-white'">
                                        <div class="aspect-video bg-surface-dark rounded-t-lg flex items-center justify-center">
                                            @php
                                                $thumbUrl = function($item) {
                                                    if ($item->thumbnail) {
                                                        if (str_starts_with($item->thumbnail, 'http')) {
                                                            return $item->thumbnail;
                                                        }
                                                        return asset('storage/' . $item->thumbnail);
                                                    }
                                                    return null;
                                                };
                                            @endphp
                                            @if($thumbUrl($mediaItem))
                                                <img src="{{ $thumbUrl($mediaItem) }}" alt="{{ $mediaItem->name }}" class="w-full h-full object-cover rounded-t-lg">
                                            @else
                                                <svg class="w-8 h-8 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="p-3">
                                            <div class="text-sm font-medium text-text">{{ $mediaItem->name }}</div>
                                            <div class="text-xs text-text-muted mt-0.5">{{ $mediaItem->duration ? gmdate('i:s', $mediaItem->duration) : '00:00' }}</div>
                                        </div>
                                        <div class="absolute top-3 right-3 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors"
                                             :class="selectedVideos.includes({{ $mediaItem->id }}) ? 'bg-primary border-primary' : 'border-border bg-white'">
                                            <svg x-show="selectedVideos.includes({{ $mediaItem->id }})" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <p class="text-sm text-text-light">No hay medios disponibles. <a href="{{ route('media.create') }}" class="text-primary hover:underline">Sube uno primero</a>.</p>
                            </div>
                        @endif

                        <input type="hidden" name="videos" :value="selectedVideos.join(',')">
                    </div>
                </div>

                {{-- Segmentation Tab --}}
                <div x-show="currentTab === 'segmentation'" x-transition>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-text mb-3">Ciudades</label>
                            <div class="space-y-2 max-h-64 overflow-y-auto border border-border rounded-lg p-3">
                                @forelse($cities as $city)
                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-surface-dark cursor-pointer transition-colors">
                                        <input type="checkbox" name="cities[]" value="{{ $city }}" class="w-4 h-4 rounded border-border text-primary focus:ring-primary">
                                        <span class="text-sm text-text">{{ $city }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-text-light p-2">No hay ciudades disponibles. <a href="{{ route('locations.create') }}" class="text-primary hover:underline">Crea una ubicación primero</a>.</p>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text mb-3">Grupos</label>
                            <div class="space-y-2 max-h-64 overflow-y-auto border border-border rounded-lg p-3">
                                @forelse($allGroups as $group)
                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-surface-dark cursor-pointer transition-colors">
                                        <input type="checkbox" name="groups[]" value="{{ $group->id }}" class="w-4 h-4 rounded border-border text-primary focus:ring-primary">
                                        <span class="text-sm text-text">{{ $group->name }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-text-light p-2">No hay grupos disponibles. <a href="{{ route('groups.create') }}" class="text-primary hover:underline">Crea un grupo primero</a>.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('campaigns.index') }}" class="px-4 py-2.5 rounded-lg text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">Crear Campaña</button>
            </div>
        </form>
    </div>
</x-app-layout>
