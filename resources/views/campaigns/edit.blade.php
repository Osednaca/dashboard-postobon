<x-app-layout>
    <x-slot name="title">Editar Campaña</x-slot>

    @php
        $allMedia = \App\Models\Media::all();
        $allLocations = \App\Models\Location::all();
        $allGroups = \App\Models\Group::all();
        $cities = $allLocations->pluck('city')->unique()->filter()->sort()->values()->toArray();
        $selectedMediaIds = $campaign->media->pluck('id')->toArray();
        $selectedCities = $campaign->segment_cities ?? [];
        $selectedGroupIds = $campaign->segment_groups ?? [];
    @endphp

    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('campaigns.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Editar Campaña</h2>
                <p class="mt-1 text-sm text-text-muted">{{ $campaign->name }}</p>
            </div>
        </div>

        <form action="{{ route('campaigns.update', $campaign) }}" method="POST" class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]" x-data="{ currentTab: 'general' }">
            @csrf
            @method('PUT')

            <div class="border-b border-border">
                <nav class="flex -mb-px px-6 pt-4">
                    <button type="button" @click="currentTab = 'general'" :class="currentTab === 'general' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">General</button>
                    <button type="button" @click="currentTab = 'videos'" :class="currentTab === 'videos' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Videos</button>
                    <button type="button" @click="currentTab = 'segmentation'" :class="currentTab === 'segmentation' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Segmentación</button>
                    <button type="button" @click="currentTab = 'devices'" :class="currentTab === 'devices' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Dispositivos</button>
                </nav>
            </div>

            <div class="p-6 space-y-6">
                <div x-show="currentTab === 'general'" x-transition>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-text mb-1.5">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" required value="{{ old('name', $campaign->name) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                            @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-text mb-1.5">Descripción</label>
                            <textarea name="description" id="description" rows="4" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors resize-none">{{ old('description', $campaign->description) }}</textarea>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            <div>
                                <label for="priority" class="block text-sm font-medium text-text mb-1.5">Prioridad (1-10)</label>
                                <input type="number" name="priority" id="priority" min="1" max="10" value="{{ old('priority', $campaign->priority) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                            </div>
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-text mb-1.5">Fecha de inicio</label>
                                <input type="date" name="start_date" id="start_date" value="{{ old('start_date', $campaign->start_date ? $campaign->start_date->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-text mb-1.5">Fecha de fin</label>
                                <input type="date" name="end_date" id="end_date" value="{{ old('end_date', $campaign->end_date ? $campaign->end_date->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="currentTab === 'videos'" x-transition x-data="{ videos: @js($campaign->media->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'duration' => $m->duration ? gmdate('i:s', $m->duration) : '00:00'])->toArray()) }">
                    <div class="space-y-4">
                        <p class="text-sm text-text-muted">Arrastra los videos para reordenar la secuencia de reproducción.</p>
                        <div class="space-y-2">
                            <template x-for="(video, index) in videos" :key="video.id">
                                <div class="flex items-center gap-3 p-3 rounded-lg border border-border bg-surface hover:bg-surface-dark transition-colors">
                                    <div class="cursor-move text-text-muted">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                        </svg>
                                    </div>
                                    <div class="w-16 h-10 rounded bg-surface-dark flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-text" x-text="video.name"></div>
                                        <div class="text-xs text-text-muted" x-text="video.duration"></div>
                                    </div>
                                    <button type="button" @click="videos.splice(index, 1)" class="p-1.5 rounded-lg text-danger hover:bg-danger/10 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        @if($allMedia->count() > count($selectedMediaIds))
                            <button type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-border text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Agregar video
                            </button>
                        @endif
                        <input type="hidden" name="videos" :value="videos.map(v => v.id).join(',')">
                    </div>
                </div>

                <div x-show="currentTab === 'segmentation'" x-transition>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-text mb-3">Ciudades</label>
                            <div class="space-y-2 max-h-64 overflow-y-auto border border-border rounded-lg p-3">
                                @forelse($cities as $city)
                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-surface-dark cursor-pointer transition-colors">
                                        <input type="checkbox" name="cities[]" value="{{ $city }}" {{ in_array($city, old('cities', $selectedCities)) ? 'checked' : '' }} class="w-4 h-4 rounded border-border text-primary focus:ring-primary">
                                        <span class="text-sm text-text">{{ $city }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-text-light p-2">No hay ciudades disponibles.</p>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text mb-3">Grupos</label>
                            <div class="space-y-2 max-h-64 overflow-y-auto border border-border rounded-lg p-3">
                                @forelse($allGroups as $group)
                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-surface-dark cursor-pointer transition-colors">
                                        <input type="checkbox" name="groups[]" value="{{ $group->id }}" {{ in_array($group->id, old('groups', $selectedGroupIds)) ? 'checked' : '' }} class="w-4 h-4 rounded border-border text-primary focus:ring-primary">
                                        <span class="text-sm text-text">{{ $group->name }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-text-light p-2">No hay grupos disponibles.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="currentTab === 'devices'" x-transition>
                    <div class="space-y-4">
                        <p class="text-sm text-text-muted">Vista previa de los dispositivos asignados según la segmentación actual.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @forelse($campaign->deviceCampaigns as $deviceCampaign)
                                @php $device = $deviceCampaign->device; @endphp
                                @if($device)
                                    <div class="rounded-lg border border-border p-4 hover:bg-surface-dark transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-text">{{ $device->name }}</div>
                                                <div class="text-xs text-text-muted">{{ $device->location?->city ?? '-' }} · {{ $device->location?->name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex items-center gap-2">
                                            @php
                                                $indicatorStatus = match($device->status) {
                                                    'active' => 'online',
                                                    'inactive' => 'offline',
                                                    'error' => 'offline',
                                                    'disabled' => 'disabled',
                                                    default => 'offline',
                                                };
                                            @endphp
                                            <x-device-status-indicator :status="$indicatorStatus" />
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div class="col-span-full text-center py-8">
                                    <p class="text-sm text-text-light">No hay dispositivos asignados a esta campaña</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('campaigns.index') }}" class="px-4 py-2.5 rounded-lg text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">Guardar Cambios</button>
            </div>
        </form>
    </div>
</x-app-layout>
