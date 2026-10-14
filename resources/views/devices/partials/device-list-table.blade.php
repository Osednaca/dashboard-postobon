<section class="device-list-surface" aria-label="Lista de dispositivos">
    @if($deviceRows->isNotEmpty())
        <div class="device-mobile-select"><label class="flex items-center gap-2 text-xs font-medium"><input type="checkbox" @change="toggleAll()" :checked="visibleRows().length > 0 && visibleRows().every(row => selectedFleetKeys.includes(row.key))" :indeterminate="visibleRows().some(row => selectedFleetKeys.includes(row.key)) && !visibleRows().every(row => selectedFleetKeys.includes(row.key))">Seleccionar visibles</label><span class="text-xs text-text-light" x-text="visibleRows().length + (visibleRows().length === 1 ? ' equipo' : ' equipos')"></span></div>
        <table class="device-list-table">
            <caption class="sr-only">Dispositivos y acciones disponibles</caption>
            <thead><tr>
                <th class="device-cell-select"><input type="checkbox" @change="toggleAll()" :checked="visibleRows().length > 0 && visibleRows().every(row => selectedFleetKeys.includes(row.key))" :indeterminate="visibleRows().some(row => selectedFleetKeys.includes(row.key)) && !visibleRows().every(row => selectedFleetKeys.includes(row.key))" aria-label="Seleccionar todos los dispositivos visibles"></th>
                <th>Dispositivo</th><th>Estado</th><th>Establecimiento</th><th>Última conexión</th><th>Contenido actual</th><th class="text-right">Acciones</th>
            </tr></thead>
            <tbody>
                @foreach($deviceRows as $index => $row)
                    <tr x-show="matches(rows[{{ $index }}])" data-device-row="{{ $row['key'] }}">
                        <td class="device-cell-select"><input type="checkbox" data-fleet-selector data-local-id="{{ $row['localId'] }}" value="{{ $row['key'] }}" :checked="selectedFleetKeys.includes({{ Js::from($row['key']) }})" @change="toggleDevice({{ Js::from($row['key']) }}, {{ Js::from($row['localId']) }}, $event.target.checked)" aria-label="Seleccionar {{ $row['name'] }}"></td>
                        <td class="device-cell-name"><div class="device-identity"><span class="device-identity-icon"><x-device-action-icon name="monitor" /></span><div class="min-w-0"><a href="{{ $row['detailsUrl'] }}" class="device-name">{{ $row['name'] }}</a><span class="device-identifier" title="{{ $row['identifier'] }}">{{ strtoupper($row['type']) }} · {{ $row['identifier'] }}</span></div></div></td>
                        <td class="device-cell-status device-status-{{ $row['status'] }}"><x-device-status-indicator :status="$row['status']" /></td>
                        <td class="device-cell-establishment"><span class="block font-medium text-text">{{ $row['establishment'] }}</span><span class="device-address">{{ $row['address'] }}</span></td>
                        <td class="device-cell-seen">{{ $row['lastSeen'] }}</td>
                        <td class="device-cell-content"><span class="device-content-label" title="{{ $row['content'] }}">{{ $row['content'] === null || $row['content'] === '' ? '—' : ($row['type'] === 'wl35' && is_numeric($row['content']) ? 'Video '.$row['content'] : $row['content']) }}</span><span class="mt-1 block text-xs text-text-light">{{ $row['content'] === null || $row['content'] === '' ? 'Sin contenido reportado' : ($row['type'] === 'wl35' ? 'En el dispositivo' : 'Archivo del dispositivo') }}</span></td>
                        <td class="device-cell-actions"><div class="device-row-actions"><div class="device-desktop-actions">@include('devices.partials.device-actions', ['variant' => 'quick'])</div><button type="button" class="device-quick-action device-more-action" @click="openActions({{ Js::from($row['key']) }}, $event.currentTarget)" :aria-expanded="actionKey === {{ Js::from($row['key']) }}" aria-haspopup="dialog" aria-controls="device-actions-{{ $index }}" aria-label="Más acciones para {{ $row['name'] }}" title="Más acciones"><x-device-action-icon name="more" /><span class="device-more-label">Más</span></button></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div x-show="visibleRows().length === 0" x-cloak class="px-6 py-14 text-center" role="status"><x-device-action-icon name="monitor" class="mx-auto mb-3 text-text-muted" /><p class="font-semibold">No hay dispositivos que coincidan</p><p class="mt-1 text-sm text-text-light">Prueba otro nombre o cambia los filtros de esta página.</p><button type="button" @click="resetFilters()" class="device-list-button mx-auto mt-4">Limpiar filtros</button></div>
        @if($devices->hasPages())<div class="border-t border-border px-4 py-4">{{ $devices->links() }}</div>@endif
    @else
        <div class="px-6 py-16 text-center"><x-device-action-icon name="monitor" class="mx-auto mb-3 text-primary" /><p class="font-semibold">No hay dispositivos</p><p class="mt-1 text-sm text-text-light">Agrega un Z2 o conecta un WL35 para empezar.</p><a href="{{ route('devices.create') }}" class="device-list-button mx-auto mt-4">Nuevo dispositivo Z2</a></div>
    @endif
</section>
@foreach($deviceRows as $index => $row)
    <div x-show="actionKey === {{ Js::from($row['key']) }}" x-cloak class="device-menu-layer">
        <div class="device-menu-backdrop" @click="closeActions()"></div>
        <section id="device-actions-{{ $index }}" data-device-menu role="dialog" aria-modal="true" aria-labelledby="device-actions-title-{{ $index }}" class="device-action-menu" :style="window.innerWidth >= 1280 ? actionPosition : {}">
            <header class="device-menu-heading"><div class="min-w-0"><h2 id="device-actions-title-{{ $index }}" class="truncate font-semibold">{{ $row['name'] }}</h2><div class="mt-1 device-status-{{ $row['status'] }}"><x-device-status-indicator :status="$row['status']" /></div></div><button type="button" @click="closeActions()" class="device-quick-action" aria-label="Cerrar acciones"><x-device-action-icon name="close" /></button></header>
            <div class="device-menu-items">@include('devices.partials.device-actions', ['variant' => 'menu'])</div>
        </section>
    </div>
@endforeach
