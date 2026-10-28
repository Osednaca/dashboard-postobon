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
        <div class="bg-white rounded-xl border border-border p-4" x-data="scheduleIndex(@js($view))">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-1 bg-surface rounded-lg p-1">
                    <button @click="setView('list')" :class="view === 'list' ? 'bg-white text-primary shadow-sm' : 'text-text-light hover:text-text'" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Lista
                    </button>
                    <button @click="setView('calendar')" :class="view === 'calendar' ? 'bg-white text-primary shadow-sm' : 'text-text-light hover:text-text'" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Calendario
                    </button>
                </div>

                <form method="GET" action="{{ route('schedules.index') }}" class="flex items-center gap-3">
                    <input type="hidden" name="view" :value="view">
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <label for="schedule-status" class="sr-only">Estado</label>
                    <select id="schedule-status" name="status" @change="$el.form.requestSubmit()" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todos los estados</option>
                        <option value="pending" @selected($status === 'pending')>Pendiente</option>
                        <option value="executed" @selected($status === 'executed')>Ejecutada</option>
                        <option value="failed" @selected($status === 'failed')>Fallida</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelada</option>
                    </select>
                    <noscript><button type="submit">Filtrar</button></noscript>
                </form>
            </div>

            <p class="mb-3 text-xs text-text-muted">Zona horaria: {{ config('app.timezone') }} · Estados actualizados cada 15 segundos.</p>
            <p x-show="refreshError" x-cloak role="status" class="mb-3 text-xs text-danger">No se pudieron actualizar los estados. Se intentará de nuevo automáticamente.</p>
            <div x-ref="results" data-schedule-results>
            {{-- List View --}}
            <div x-show="view === 'list'" x-transition>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">Nombre</th>
                                <th class="px-6 py-4">Tipo</th>
                                <th class="px-6 py-4">Dispositivo/Grupo</th>
                                <th class="px-6 py-4">Frecuencia</th>
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
                                            'format_sd' => ['label' => 'Formatear SD', 'color' => 'text-warning'],
                                        ];
                                        $type = $types[$schedule->type] ?? ['label' => $schedule->type, 'color' => 'text-text'];
                                        @endphp
                                        <span class="text-sm font-medium {{ $type['color'] }}">{{ $type['label'] }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-text">{{ $schedule->targetLabel() }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-text">{{ $schedule->recurrenceLabel() }}</div>
                                        @if($schedule->recurrence_ends_at)
                                            <div class="mt-0.5 text-xs text-text-muted">Hasta {{ $schedule->recurrence_ends_at->format('d/m/Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-text">{{ $schedule->scheduled_at?->timezone(config('app.timezone'))->format('d/m/Y · H:i') ?? '—' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <x-status-badge :status="$schedule->status ?? 'pending'" />
                                        @if($schedule->last_run_status === 'failed')
                                            <div class="mt-1 text-xs text-danger">La última ejecución falló</div>
                                        @endif
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
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-12 h-12 rounded-full bg-surface-dark flex items-center justify-center">
                                                <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <p class="text-text-muted">{{ $status ? 'No hay programaciones con este estado' : 'No hay programaciones registradas' }}</p>
                                            <a href="{{ route('schedules.create') }}" class="text-primary text-sm font-medium hover:underline">Crear primera programación</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Calendar View: persisted dates, independent of list pagination. --}}
            <div x-show="view === 'calendar'" x-cloak class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('schedules.index', ['view' => 'calendar', 'month' => $month->subMonth()->format('Y-m'), 'status' => $status]) }}" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" aria-label="Mes anterior">← <span class="hidden sm:inline">Anterior</span></a>
                    <h3 class="text-lg font-semibold text-text">{{ ucfirst($month->locale('es')->translatedFormat('F Y')) }}</h3>
                    <a href="{{ route('schedules.index', ['view' => 'calendar', 'month' => $month->addMonth()->format('Y-m'), 'status' => $status]) }}" class="rounded-lg border border-border px-3 py-2 text-sm text-primary" aria-label="Mes siguiente"><span class="hidden sm:inline">Siguiente</span> →</a>
                </div>
                <p class="text-xs text-text-muted">Las programaciones recurrentes muestran su próxima ejecución registrada.</p>
                @if($calendarSchedules->isEmpty())
                    <p class="rounded-lg bg-surface p-4 text-sm text-text-muted">No hay programaciones{{ $status ? ' con este estado' : '' }} en este mes.</p>
                @endif
                {{-- A chronological agenda keeps event links readable without horizontal scrolling on phones. --}}
                <div class="space-y-3 md:hidden" aria-label="Programaciones del mes">
                    @for($date = $calendarStart; $date->lte($calendarEnd); $date = $date->addDay())
                        @php($events = $calendarSchedules->get($date->format('Y-m-d'), collect()))
                        @if($date->month === $month->month && $events->isNotEmpty())
                            <section data-calendar-mobile-date="{{ $date->format('Y-m-d') }}" class="rounded-lg border border-border p-3">
                                <h4 class="text-sm font-semibold text-text {{ $date->isToday() ? 'text-primary' : '' }}">
                                    <time datetime="{{ $date->format('Y-m-d') }}">{{ ucfirst($date->locale('es')->translatedFormat('l d \d\e F')) }}</time>
                                </h4>
                                @foreach($events->take(1) as $event)
                                    @include('schedules.partials.calendar-event', ['event' => $event])
                                @endforeach
                                @if($events->count() > 1)
                                    <details data-calendar-more="mobile-{{ $date->format('Y-m-d') }}">
                                        <summary class="flex min-h-11 cursor-pointer items-center text-sm font-medium text-primary">Ver {{ $events->count() - 1 }} más</summary>
                                        @foreach($events->skip(1) as $event)
                                            @include('schedules.partials.calendar-event', ['event' => $event])
                                        @endforeach
                                    </details>
                                @endif
                            </section>
                        @endif
                    @endfor
                </div>
                <div class="hidden md:block" aria-label="Calendario mensual">
                    <div class="grid grid-cols-7 gap-2 mb-2">
                        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $day)
                            <div class="text-center text-xs font-medium text-text-muted py-2">{{ $day }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-2">
                        @for($date = $calendarStart; $date->lte($calendarEnd); $date = $date->addDay())
                            @php($events = $calendarSchedules->get($date->format('Y-m-d'), collect()))
                            <div data-calendar-date="{{ $date->format('Y-m-d') }}" class="min-w-0 min-h-28 rounded-lg border border-border p-2 {{ $date->month !== $month->month ? 'bg-surface text-text-muted' : 'text-text' }}">
                                <time datetime="{{ $date->format('Y-m-d') }}" class="text-sm {{ $date->isToday() ? 'font-bold text-primary' : '' }}">{{ $date->day }}</time>
                                @if($date->month === $month->month)
                                    @foreach($events->take(2) as $event)
                                        @include('schedules.partials.calendar-event', ['event' => $event])
                                    @endforeach
                                    @if($events->count() > 2)
                                        <details data-calendar-more="desktop-{{ $date->format('Y-m-d') }}">
                                            <summary class="mt-2 cursor-pointer text-xs font-medium text-primary">Ver {{ $events->count() - 2 }} más</summary>
                                            @foreach($events->skip(2) as $event)
                                                @include('schedules.partials.calendar-event', ['event' => $event])
                                            @endforeach
                                        </details>
                                    @endif
                                @endif
                            </div>
                        @endfor
                    </div>
                </div>
            </div>

            @if(isset($schedules) && $schedules->hasPages())
                <div x-show="view === 'list'" data-schedule-pagination class="border-t border-border">
                    <x-pagination :paginator="$schedules" />
                </div>
            @endif
            </div>
        </div>
    </div>
</x-app-layout>
