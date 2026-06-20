<x-app-layout>
    <x-slot name="title">Programación</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Programación</h2>
                <p class="mt-1 text-sm text-text-muted">Gestiona las tareas programadas del sistema</p>
            </div>
            <a href="{{ route('schedules.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nueva Programación
            </a>
        </div>

        {{-- View Toggle --}}
        <div class="bg-white rounded-xl border border-border p-4" x-data="{ view: 'list' }">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-1 bg-surface rounded-lg p-1">
                    <button @click="view = 'list'" :class="view === 'list' ? 'bg-white text-primary shadow-sm' : 'text-text-light hover:text-text'" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Lista
                    </button>
                    <button @click="view = 'calendar'" :class="view === 'calendar' ? 'bg-white text-primary shadow-sm' : 'text-text-light hover:text-text'" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Calendario
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <select class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todos los estados</option>
                        <option value="pending">Pendiente</option>
                        <option value="executed">Ejecutada</option>
                        <option value="failed">Fallida</option>
                    </select>
                </div>
            </div>

            {{-- List View --}}
            <div x-show="view === 'list'" x-transition>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">Nombre</th>
                                <th class="px-6 py-4">Tipo</th>
                                <th class="px-6 py-4">Dispositivo/Grupo</th>
                                <th class="px-6 py-4">Fecha programada</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($schedules ?? [] as $schedule)
                                <tr class="hover:bg-surface/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-text">{{ $schedule->name ?? 'Programación Ejemplo' }}</div>
                                        <div class="text-xs text-text-muted mt-0.5">{{ $schedule->type ? '' : '' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                        $types = [
                                            'power_on' => ['label' => 'Encendido', 'color' => 'text-success'],
                                            'power_off' => ['label' => 'Apagado', 'color' => 'text-danger'],
                                            'change_content' => ['label' => 'Cambio Contenido', 'color' => 'text-info'],
                                            'activate_campaign' => ['label' => 'Activar Campaña', 'color' => 'text-primary'],
                                        ];
                                        $type = $types[$schedule->type ?? 'power_on'];
                                        @endphp
                                        <span class="text-sm font-medium {{ $type['color'] }}">{{ $type['label'] }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-text">{{ $schedule->device?->name ?? $schedule->group?->name ?? 'Todos' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-text">{{ $schedule->scheduled_at ?? '2024-06-15 10:00' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <x-status-badge :status="$schedule->status ?? 'pending'" />
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-action-button href="{{ route('schedules.execute', $schedule->id ?? 1) }}" icon="M13 10V3L4 14h7v7l9-11h-7z" label="Ejecutar ahora" color="success" method="POST" confirm="true" />
                                            <x-action-button href="{{ route('schedules.edit', $schedule->id ?? 1) }}" icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" label="Editar" color="primary" />
                                            <x-action-button href="{{ route('schedules.show', $schedule->id ?? 1) }}" icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" label="Ver" />
                                            <x-action-button href="{{ route('schedules.destroy', $schedule->id ?? 1) }}" icon="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" label="Eliminar" color="danger" method="DELETE" confirm="true" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-12 h-12 rounded-full bg-surface-dark flex items-center justify-center">
                                                <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <p class="text-text-muted">No hay programaciones registradas</p>
                                            <a href="{{ route('schedules.create') }}" class="text-primary text-sm font-medium hover:underline">Crear primera programación</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Calendar View --}}
            <div x-show="view === 'calendar'" x-transition class="p-4">
                <div class="grid grid-cols-7 gap-2 mb-2">
                    @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $day)
                        <div class="text-center text-xs font-medium text-text-muted py-2">{{ $day }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-2">
                    @for($i = 1; $i <= 30; $i++)
                        <div class="aspect-square rounded-lg border border-border p-2 hover:bg-surface-dark transition-colors relative cursor-pointer">
                            <span class="text-sm text-text">{{ $i }}</span>
                            @if(in_array($i, [5, 12, 15, 20, 25]))
                                <div class="absolute bottom-1.5 left-1.5 right-1.5">
                                    <div class="w-full h-1.5 rounded-full bg-primary"></div>
                                </div>
                            @endif
                            @if(in_array($i, [8, 18]))
                                <div class="absolute bottom-1.5 left-1.5 right-1.5">
                                    <div class="w-full h-1.5 rounded-full bg-warning mt-2.5"></div>
                                </div>
                            @endif
                        </div>
                    @endfor
                </div>
                <div class="mt-4 flex items-center gap-4 text-xs">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-1.5 rounded-full bg-primary"></div>
                        <span class="text-text-muted">Ejecutada</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-1.5 rounded-full bg-warning"></div>
                        <span class="text-text-muted">Pendiente</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-1.5 rounded-full bg-danger"></div>
                        <span class="text-text-muted">Fallida</span>
                    </div>
                </div>
            </div>

            @if(isset($schedules) && $schedules->hasPages())
                <div class="border-t border-border">
                    <x-pagination :paginator="$schedules" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
