@extends('layouts.app')

@section('title', 'Grupos')

@section('content')
<div x-data="{ search: '' }" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-text">Grupos</h1><p class="mt-1 text-sm text-text-light">Organiza tus dispositivos y consulta los establecimientos de cada grupo.</p></div>
        @can('create', App\Models\Group::class)
            <a href="{{ route('groups.create') }}" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary/90">Nuevo Grupo</a>
        @endcan
    </div>
    <div class="rounded-xl border border-border bg-white p-4">
        <label for="group-search" class="mb-2 block text-sm font-medium text-text">Buscar grupo</label>
        <input id="group-search" type="search" x-model="search" placeholder="Nombre, ciudad o tipo de negocio" class="w-full rounded-lg border border-border px-4 py-2.5 text-sm focus:border-primary focus:ring-primary/20">
        @if($groups->hasPages())<p class="mt-2 text-xs text-text-muted">La búsqueda filtra los grupos de esta página.</p>@endif
    </div>
    <div class="overflow-hidden rounded-xl border border-border bg-white">
        @if($groups->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-surface"><tr><th class="px-5 py-4 font-semibold">Grupo</th><th class="px-5 py-4 font-semibold">Tipo de negocio</th><th class="px-5 py-4 font-semibold">Ubicación</th><th class="px-5 py-4 font-semibold">Dispositivos</th><th class="px-5 py-4 text-right font-semibold">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-border">
                        @foreach($groups as $group)
                            @php($totalDevices = $group->devices_count + $group->wl35_devices_count)
                            <tr class="hover:bg-surface/50" data-search="{{ mb_strtolower(implode(' ', [$group->name, $group->description, $group->city, $group->address, $group->businessType?->name])) }}" x-show="!search || $el.dataset.search.includes(search.toLocaleLowerCase('es'))">
                                <td class="px-5 py-4"><a href="{{ route('groups.show', $group) }}" class="font-semibold text-primary hover:underline">{{ $group->name }}</a><p class="mt-1 max-w-xs text-xs text-text-light">{{ $group->description }}</p></td>
                                <td class="px-5 py-4 text-text-light">{{ $group->businessType?->name ?? 'Sin clasificación' }}</td>
                                <td class="px-5 py-4 text-text-light">{{ $group->city ?: ($group->address ?: 'Sin ubicación') }}</td>
                                <td class="px-5 py-4"><span class="font-medium">{{ $totalDevices }} dispositivo{{ $totalDevices !== 1 ? 's' : '' }}</span><p class="mt-1 text-xs text-text-muted">Modelo 42: {{ $group->devices_count }} · Modelo 35: {{ $group->wl35_devices_count }}</p></td>
                                <td class="px-5 py-4"><div class="flex flex-wrap justify-end gap-3">
                                    <a href="{{ route('groups.show', $group) }}" class="font-medium text-primary hover:underline">Ver detalles</a>
                                    @can('update', $group)<a href="{{ route('groups.edit', $group) }}" class="font-medium text-text-light hover:underline">Editar</a>@endcan
                                    @can('delete', $group)
                                        <form action="{{ route('groups.destroy', $group) }}" method="POST" x-data="{ confirming: false }" @submit="if (!confirming) { $event.preventDefault(); confirming = true; }">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="font-medium text-danger hover:underline" x-text="confirming ? 'Confirmar eliminación' : 'Eliminar'">Eliminar</button>
                                            <button type="button" x-show="confirming" x-cloak @click="confirming = false" class="ml-2 text-text-light">Cancelar</button>
                                        </form>
                                    @endcan
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($groups->hasPages())<div class="border-t border-border px-5 py-4">{{ $groups->links() }}</div>@endif
        @else
            <div class="p-12 text-center"><h2 class="font-semibold text-text">No hay grupos</h2><p class="mt-2 text-sm text-text-light">Crea un grupo y selecciona los equipos que quieras organizar.</p></div>
        @endif
    </div>
</div>
@endsection
