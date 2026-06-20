@extends('layouts.app')

@section('title', 'Ubicaciones')

@section('content')
<div x-data="{ search: '', city: '', country: '', showDeleteModal: false, deleteId: null, deleteName: '' }">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-text">Ubicaciones</h1>
            <p class="text-sm text-text-light mt-1">Gestiona las ubicaciones de tus dispositivos</p>
        </div>
        <a href="{{ route('locations.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nueva Ubicación
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-border p-4 mb-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Buscar por nombre, dirección o contacto..." class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
            </div>
            <div class="sm:w-48">
                <input type="text" x-model="city" placeholder="Filtrar por ciudad" class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
            </div>
            <div class="sm:w-48">
                <input type="text" x-model="country" placeholder="Filtrar por país" class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
        @if($locations->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="px-6 py-4 font-semibold text-text">Nombre</th>
                            <th class="px-6 py-4 font-semibold text-text">Dirección</th>
                            <th class="px-6 py-4 font-semibold text-text">Ciudad</th>
                            <th class="px-6 py-4 font-semibold text-text">País</th>
                            <th class="px-6 py-4 font-semibold text-text">Dispositivos</th>
                            <th class="px-6 py-4 font-semibold text-text">Contacto</th>
                            <th class="px-6 py-4 font-semibold text-text">Teléfono</th>
                            <th class="px-6 py-4 font-semibold text-text text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($locations as $location)
                            <tr class="hover:bg-surface/50 transition-colors"
                                x-show="
                                    (!search || '{{ strtolower($location->name) }}'.includes(search.toLowerCase()) || '{{ strtolower($location->address) }}'.includes(search.toLowerCase()) || '{{ strtolower($location->contact_name) }}'.includes(search.toLowerCase())) &&
                                    (!city || '{{ strtolower($location->city) }}'.includes(city.toLowerCase())) &&
                                    (!country || '{{ strtolower($location->country) }}'.includes(country.toLowerCase()))
                                ">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </div>
                                        <span class="font-medium text-text">{{ $location->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-text-light">{{ $location->address ?? '-' }}</td>
                                <td class="px-6 py-4 text-text-light">{{ $location->city ?? '-' }}</td>
                                <td class="px-6 py-4 text-text-light">{{ $location->country ?? '-' }}</td>
                                <td class="px-6 py-4 text-text-light">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-info/10 text-info text-xs font-medium">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        {{ $location->devices->count() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-text-light">{{ $location->contact_name ?? '-' }}</td>
                                <td class="px-6 py-4 text-text-light">{{ $location->phone ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('locations.edit', $location) }}" class="p-2 rounded-lg text-text-light hover:text-primary hover:bg-primary/10 transition-colors" title="Editar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('locations.show', $location) }}" class="p-2 rounded-lg text-text-light hover:text-info hover:bg-info/10 transition-colors" title="Ver detalles">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <button @click="showDeleteModal = true; deleteId = {{ $location->id }}; deleteName = '{{ $location->name }}'" class="p-2 rounded-lg text-text-light hover:text-danger hover:bg-danger/10 transition-colors" title="Eliminar">
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

            <!-- Pagination -->
            @if($locations->hasPages())
                <div class="px-6 py-4 border-t border-border">
                    {{ $locations->links() }}
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="flex flex-col items-center justify-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-surface flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text mb-1">No hay ubicaciones</h3>
                <p class="text-sm text-text-light mb-4">Aún no has registrado ninguna ubicación.</p>
                <a href="{{ route('locations.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Crear primera ubicación
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
                    <h3 class="text-lg font-semibold text-text">¿Eliminar ubicación?</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-6">Estás a punto de eliminar <span class="font-medium text-text" x-text="deleteName"></span>. Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button @click="showDeleteModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                <form :action="'/locations/' + deleteId" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-danger text-white text-sm font-medium hover:bg-red-700 transition-colors">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
