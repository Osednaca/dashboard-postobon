@if(! $canOperateDevices)
    @push('styles')
        <style>
            .device-list-readonly th { width: 18%; }
            .device-list-readonly th:first-child { width: 28%; }
            .device-list-readonly th:last-child { width: 18%; }
            @media (max-width: 1279px) {
                .device-list-readonly tbody tr { grid-template-columns: minmax(0, 1fr); }
                .device-list-readonly .device-cell-name,
                .device-list-readonly .device-cell-status,
                .device-list-readonly .device-cell-establishment,
                .device-list-readonly .device-cell-seen { grid-column: 1; }
            }
        </style>
    @endpush
@endif
<section class="device-list-surface" aria-label="Lista de dispositivos">
    @if($deviceRows->isNotEmpty())
        @if($canOperateDevices)
            <div class="device-mobile-select"><label class="flex items-center gap-2 text-xs font-medium"><input type="checkbox" @change="toggleAll()" :checked="visibleRows().length > 0 && visibleRows().every(row => selectedFleetKeys.includes(row.key))" :indeterminate="visibleRows().some(row => selectedFleetKeys.includes(row.key)) && !visibleRows().every(row => selectedFleetKeys.includes(row.key))">Seleccionar visibles</label><span class="text-xs text-text-light" x-text="visibleRows().length + (visibleRows().length === 1 ? ' equipo' : ' equipos')"></span></div>
        @endif
        <table class="device-list-table {{ $canOperateDevices ? '' : 'device-list-readonly' }}">
            <caption class="sr-only">{{ $canOperateDevices ? 'Dispositivos y acciones disponibles' : 'Información de dispositivos' }}</caption>
            <thead><tr>
                @if($canOperateDevices)
                    <th class="device-cell-select"><input type="checkbox" @change="toggleAll()" :checked="visibleRows().length > 0 && visibleRows().every(row => selectedFleetKeys.includes(row.key))" :indeterminate="visibleRows().some(row => selectedFleetKeys.includes(row.key)) && !visibleRows().every(row => selectedFleetKeys.includes(row.key))" aria-label="Seleccionar todos los dispositivos visibles"></th>
                @endif
                <th>Dispositivo</th><th>Estado</th><th>Establecimiento</th><th>Última conexión</th><th>Contenido actual</th>@if($canOperateDevices)<th class="text-right">Acciones</th>@endif
            </tr></thead>
            <tbody>
                @foreach($deviceRows as $index => $row)
                    <tr x-show="matches(rows[{{ $index }}])" data-device-row="{{ $row['key'] }}">
                        @if($canOperateDevices)
                            <td class="device-cell-select"><input type="checkbox" data-fleet-selector data-local-id="{{ $row['localId'] }}" value="{{ $row['key'] }}" :checked="selectedFleetKeys.includes({{ Js::from($row['key']) }})" @change="toggleDevice({{ Js::from($row['key']) }}, {{ Js::from($row['localId']) }}, $event.target.checked)" aria-label="Seleccionar {{ $row['name'] }}"></td>
                        @endif
                        <td class="device-cell-name"><div class="device-identity"><span class="device-identity-icon"><x-device-action-icon name="monitor" /></span><div class="min-w-0"><a href="{{ $row['detailsUrl'] }}" class="device-name">{{ $row['name'] }}</a><span class="device-identifier" title="{{ $row['identifier'] }}">{{ $row['type'] === 'wl35' ? '35' : '42' }} · {{ $row['identifier'] }}</span></div></div></td>
                        <td class="device-cell-status"><x-device-live-status :row="'rows['.$index.']'" /></td>
                        <td class="device-cell-establishment"><span class="block font-medium text-text">{{ $row['establishment'] }}</span><span class="device-address">{{ $row['address'] }}</span></td>
                        <td class="device-cell-seen" x-text="rows[{{ $index }}].lastSeen">{{ $row['lastSeen'] }}</td>
                        <td class="device-cell-content"><span class="device-content-label" :title="rows[{{ $index }}].content" x-text="rows[{{ $index }}].content == null ? '—' : (rows[{{ $index }}].type === 'wl35' ? 'Video ' : '') + rows[{{ $index }}].content"></span><span class="mt-1 block text-xs text-text-light" x-text="rows[{{ $index }}].content == null ? 'Sin contenido reportado' : 'En el dispositivo'"></span></td>
                        @if($canOperateDevices)
                            <td class="device-cell-actions"><div class="device-row-actions"><div class="device-desktop-actions">@include('devices.partials.device-actions', ['variant' => 'quick'])</div><button type="button" class="device-quick-action device-more-action" @click="openActions({{ Js::from($row['key']) }}, $event.currentTarget)" :aria-expanded="actionKey === {{ Js::from($row['key']) }}" aria-haspopup="dialog" aria-controls="device-actions-{{ $index }}" aria-label="Más acciones para {{ $row['name'] }}" title="Más acciones"><x-device-action-icon name="more" /><span class="device-more-label">Más</span></button></div></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div x-show="visibleRows().length === 0" x-cloak class="px-6 py-14 text-center" role="status"><x-device-action-icon name="monitor" class="mx-auto mb-3 text-text-muted" /><p class="font-semibold">No hay dispositivos que coincidan</p><p class="mt-1 text-sm text-text-light">Prueba otro nombre o cambia los filtros de esta página.</p><button type="button" @click="resetFilters()" class="device-list-button mx-auto mt-4">Limpiar filtros</button></div>
        @if($devices->hasPages())<div class="border-t border-border px-4 py-4">{{ $devices->links() }}</div>@endif
    @else
        <div class="px-6 py-16 text-center">
            <x-device-action-icon name="monitor" class="mx-auto mb-3 text-primary" /><p class="font-semibold">No hay dispositivos</p>
            @if($canOperateDevices)
                <p class="mt-1 text-sm text-text-light">Agrega un 42 o conecta un 35 para empezar.</p><a href="{{ route('devices.create') }}" class="device-list-button mx-auto mt-4">Nuevo dispositivo 42</a>
            @else
                <p class="mt-1 text-sm text-text-light">Los equipos registrados aparecerán aquí para consultar su información.</p>
            @endif
        </div>
    @endif
</section>
@if($canOperateDevices)
@foreach($deviceRows as $index => $row)
    <div x-show="actionKey === {{ Js::from($row['key']) }}" x-cloak class="device-menu-layer">
        <div class="device-menu-backdrop" @click="closeActions()"></div>
        <section id="device-actions-{{ $index }}" data-device-menu role="dialog" aria-modal="true" aria-labelledby="device-actions-title-{{ $index }}" class="device-action-menu" :style="window.innerWidth >= 1280 ? actionPosition : {}">
            <header class="device-menu-heading"><div class="min-w-0"><h2 id="device-actions-title-{{ $index }}" class="truncate font-semibold">{{ $row['name'] }}</h2><div class="mt-1 device-status-{{ $row['status'] }}"><x-device-live-status :row="'rows['.$index.']'" /></div></div><button type="button" @click="closeActions()" class="device-quick-action" aria-label="Cerrar acciones"><x-device-action-icon name="close" /></button></header>
            <div class="device-menu-items">@include('devices.partials.device-actions', ['variant' => 'menu'])</div>
        </section>
    </div>
@endforeach
@endif
