@extends('layouts.app')

@section('title', 'Biblioteca Multimedia')

@section('content')
<div x-data="{ 
    search: '', 
    typeFilter: '', 
    viewMode: 'grid', 
    showDeleteModal: false, 
    deleteId: null, 
    deleteName: '',
    showAssignModal: false,
    assignId: null
}">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-text">Biblioteca Multimedia</h1>
            <p class="text-sm text-text-light mt-1">Gestiona tus videos y contenido multimedia</p>
        </div>
        <div class="flex items-center gap-3">
            <!-- View Toggle -->
            <div class="flex items-center bg-white rounded-lg border border-border overflow-hidden">
                <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-primary text-white' : 'text-text-light hover:text-text'" class="p-2.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                </button>
                <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-primary text-white' : 'text-text-light hover:text-text'" class="p-2.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
            <a href="{{ route('media.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Subir Video
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-border p-4 mb-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Buscar por nombre..." class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
            </div>
            <div class="sm:w-40">
                <select x-model="typeFilter" class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                    <option value="">Todos los tipos</option>
                    <option value="video">Video</option>
                    <option value="image">Imagen</option>
                </select>
            </div>
        </div>
    </div>

    @php
        $mediaUrl = function($item) {
            if (str_starts_with($item->file_path, 'http')) {
                return $item->file_path;
            }
            return asset('storage/' . $item->file_path);
        };
        $thumbnailUrl = function($item) {
            if ($item->thumbnail) {
                if (str_starts_with($item->thumbnail, 'http')) {
                    return $item->thumbnail;
                }
                return asset('storage/' . $item->thumbnail);
            }
            return null;
        };
    @endphp

    <!-- Grid View -->
    <div x-show="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($media as $item)
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden hover:shadow-[0_4px_12px_rgba(0,0,0,0.08)] transition-shadow"
                 x-show="(!search || '{{ strtolower($item->name) }}'.includes(search.toLowerCase())) && (!typeFilter || '{{ $item->mime_type }}'.startsWith(typeFilter))">
                <!-- Thumbnail -->
                <div class="aspect-video bg-surface relative group">
                    @if($thumbnailUrl($item))
                        <img src="{{ $thumbnailUrl($item) }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <a href="{{ route('media.show', $item) }}" class="p-2 rounded-full bg-white/90 text-text hover:text-primary transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <h3 class="font-semibold text-text text-sm mb-1 truncate" title="{{ $item->name }}">{{ $item->name }}</h3>
                    <div class="flex items-center gap-3 text-xs text-text-light mb-3">
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $item->duration ? gmdate('i:s', $item->duration) : '-' }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                            </svg>
                            {{ $item->size ? number_format($item->size / 1024 / 1024, 2) . ' MB' : '-' }}
                        </span>
                        <span>{{ $item->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('media.show', $item) }}" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-primary/10 text-primary text-xs font-medium hover:bg-primary/20 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Vista previa
                        </a>
                        <button @click="showAssignModal = true; assignId = {{ $item->id }}" class="p-2 rounded-lg bg-surface text-text-light hover:text-primary hover:bg-primary/10 transition-colors" title="Asignar a campaña">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                        <button @click="showDeleteModal = true; deleteId = {{ $item->id }}; deleteName = '{{ $item->name }}'" class="p-2 rounded-lg bg-surface text-text-light hover:text-danger hover:bg-danger/10 transition-colors" title="Eliminar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-surface flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text mb-1">No hay multimedia</h3>
                <p class="text-sm text-text-light mb-4">Aún no has subido ningún archivo.</p>
                <a href="{{ route('media.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Subir primer video
                </a>
            </div>
        @endforelse
    </div>

    <!-- List View -->
    <div x-show="viewMode === 'list'" class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
        @if($media->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="px-6 py-4 font-semibold text-text">Nombre</th>
                            <th class="px-6 py-4 font-semibold text-text">Duración</th>
                            <th class="px-6 py-4 font-semibold text-text">Tamaño</th>
                            <th class="px-6 py-4 font-semibold text-text">Fecha</th>
                            <th class="px-6 py-4 font-semibold text-text">Tipo</th>
                            <th class="px-6 py-4 font-semibold text-text text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($media as $item)
                            <tr class="hover:bg-surface/50 transition-colors"
                                x-show="(!search || '{{ strtolower($item->name) }}'.includes(search.toLowerCase())) && (!typeFilter || '{{ $item->mime_type }}'.startsWith(typeFilter))">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-surface flex items-center justify-center shrink-0">
                                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <span class="font-medium text-text">{{ $item->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-text-light">{{ $item->duration ? gmdate('i:s', $item->duration) : '-' }}</td>
                                <td class="px-6 py-4 text-text-light">{{ $item->size ? number_format($item->size / 1024 / 1024, 2) . ' MB' : '-' }}</td>
                                <td class="px-6 py-4 text-text-light">{{ $item->created_at->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-text-light">{{ $item->mime_type ? explode('/', $item->mime_type)[0] : '-' }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('media.show', $item) }}" class="p-2 rounded-lg text-text-light hover:text-primary hover:bg-primary/10 transition-colors" title="Vista previa">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <button @click="showAssignModal = true; assignId = {{ $item->id }}" class="p-2 rounded-lg text-text-light hover:text-primary hover:bg-primary/10 transition-colors" title="Asignar a campaña">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </button>
                                        <button @click="showDeleteModal = true; deleteId = {{ $item->id }}; deleteName = '{{ $item->name }}'" class="p-2 rounded-lg text-text-light hover:text-danger hover:bg-danger/10 transition-colors" title="Eliminar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($media->hasPages())
                <div class="px-6 py-4 border-t border-border">
                    {{ $media->links() }}
                </div>
            @endif
        @else
            <div class="flex flex-col items-center justify-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-surface flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text mb-1">No hay multimedia</h3>
                <p class="text-sm text-text-light mb-4">Aún no has subido ningún archivo.</p>
                <a href="{{ route('media.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Subir primer video
                </a>
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border p-6 w-full max-w-md" @click.away="showDeleteModal = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-danger/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text">¿Eliminar archivo?</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-6">Estás a punto de eliminar <span class="font-medium text-text" x-text="deleteName"></span>. Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button @click="showDeleteModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                <form :action="'/media/' + deleteId" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-danger text-white text-sm font-medium hover:bg-red-700 transition-colors">Eliminar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign to Campaign Modal -->
    <div x-show="showAssignModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAssignModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border p-6 w-full max-w-md" @click.away="showAssignModal = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text">Asignar a Campaña</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-4">Selecciona una campaña para asignar este archivo.</p>
            <form :action="'/media/' + assignId + '/assign'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="campaign_id" class="block text-sm font-medium text-text mb-2">Campaña</label>
                    <select id="campaign_id" name="campaign_id" required class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                        <option value="">Seleccionar campaña</option>
                        @foreach(App\Models\Campaign::all() as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showAssignModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-red-700 transition-colors">Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
