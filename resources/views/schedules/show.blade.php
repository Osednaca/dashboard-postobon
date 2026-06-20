<x-app-layout>
    <x-slot name="title">Detalle de Programación</x-slot>

    @php $schedule = $schedule ?? (object)['id' => 1, 'name' => 'Apagado nocturno', 'type' => 'power_off', 'device_name' => 'Fan 3D 001', 'group_name' => 'Centros Comerciales', 'scheduled_at' => '2024-06-15 22:00:00', 'status' => 'executed', 'created_at' => '2024-06-01 10:00:00']; @endphp

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('schedules.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-text">{{ $schedule->name }}</h2>
                        <x-status-badge :status="$schedule->status" />
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('schedules.edit', $schedule->id) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Editar
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Details Card --}}
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text uppercase tracking-wider mb-4">Detalles</h3>
                <div class="space-y-4">
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Tipo</span>
                        <span class="text-sm font-medium text-text">{{ $schedule->type === 'power_off' ? 'Apagado' : ($schedule->type === 'power_on' ? 'Encendido' : ($schedule->type === 'change_content' ? 'Cambio Contenido' : 'Activar Campaña')) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Dispositivo</span>
                        <span class="text-sm font-medium text-text">{{ $schedule->device?->name ?? 'Todos' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Grupo</span>
                        <span class="text-sm font-medium text-text">{{ $schedule->group?->name ?? 'Todos' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Fecha programada</span>
                        <span class="text-sm font-medium text-text">{{ $schedule->scheduled_at }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Estado</span>
                        <x-status-badge :status="$schedule->status" />
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Creada</span>
                        <span class="text-sm font-medium text-text">{{ $schedule->created_at }}</span>
                    </div>
                </div>
            </div>

            {{-- Execution History --}}
            <div class="lg:col-span-2 bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="px-6 py-4 border-b border-border">
                    <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Historial de ejecución</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3">Fecha</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3">Duración</th>
                                <th class="px-6 py-3">Resultado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @php $executions = [
                                ['date' => '2024-06-15 22:00:05', 'status' => 'success', 'duration' => '2.3s', 'result' => 'Dispositivo apagado correctamente'],
                                ['date' => '2024-06-14 22:00:03', 'status' => 'success', 'duration' => '1.8s', 'result' => 'Dispositivo apagado correctamente'],
                                ['date' => '2024-06-13 22:00:12', 'status' => 'warning', 'duration' => '5.1s', 'result' => 'Retraso por latencia de red'],
                                ['date' => '2024-06-12 22:00:02', 'status' => 'success', 'duration' => '1.5s', 'result' => 'Dispositivo apagado correctamente'],
                            ]; @endphp
                            @foreach($executions as $execution)
                                <tr class="hover:bg-surface/50 transition-colors">
                                    <td class="px-6 py-3 text-text">{{ $execution['date'] }}</td>
                                    <td class="px-6 py-3">
                                        @if($execution['status'] === 'success')
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-success">
                                                <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                                Éxito
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-warning">
                                                <span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                                                Advertencia
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-text">{{ $execution['duration'] }}</td>
                                    <td class="px-6 py-3 text-text-light">{{ $execution['result'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Logs --}}
        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
            <div class="px-6 py-4 border-b border-border flex items-center justify-between">
                <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Registros (Logs)</h3>
                <button class="text-sm text-primary hover:underline">Descargar</button>
            </div>
            <div class="p-4 bg-surface font-mono text-xs space-y-2 overflow-x-auto">
                <div class="text-success">[2024-06-15 22:00:00] INFO: Iniciando tarea "Apagado nocturno"</div>
                <div class="text-text-muted">[2024-06-15 22:00:01] INFO: Enviando comando a dispositivo Fan 3D 001</div>
                <div class="text-text-muted">[2024-06-15 22:00:03] INFO: Dispositivo Fan 3D 001 respondió ACK</div>
                <div class="text-success">[2024-06-15 22:00:05] INFO: Dispositivo apagado correctamente</div>
                <div class="text-text-muted">[2024-06-15 22:00:05] INFO: Tarea completada en 2.3s</div>
            </div>
        </div>
    </div>
</x-app-layout>
