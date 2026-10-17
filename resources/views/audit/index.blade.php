<x-app-layout>
    <x-slot name="title">Auditoría</x-slot>

    @php
        $actionLabels = [
            'create' => 'Creación', 'update' => 'Actualización', 'delete' => 'Eliminación',
            'login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión', 'sync' => 'Sincronización',
            'assign' => 'Asignación', 'unassign' => 'Desasignación', 'publish' => 'Publicación',
            'unpublish' => 'Retiro de publicación', 'activate' => 'Activación', 'deactivate' => 'Desactivación',
            'schedule' => 'Programación', 'execute' => 'Ejecución', 'restart' => 'Reinicio',
            'export' => 'Exportación', 'import' => 'Importación', 'approve' => 'Aprobación', 'reject' => 'Rechazo',
            'assign_establishment' => 'Asignación de establecimiento', 'add_group_devices' => 'Dispositivos añadidos al grupo',
        ];
        $entityLabels = [
            'user' => 'Usuario', 'device' => 'Dispositivo 42', 'wl35deviceprofile' => 'Dispositivo 35',
            'campaign' => 'Campaña', 'media' => 'Medio', 'group' => 'Grupo', 'schedule' => 'Programación',
            'establishment' => 'Establecimiento', 'notification' => 'Notificación', 'setting' => 'Configuración',
            'location' => 'Ubicación', 'subscription' => 'Suscripción',
        ];
    @endphp

    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-text">Auditoría</h2>
            <p class="mt-1 text-sm text-text-muted">Historial de acciones registradas en el sistema</p>
        </div>

        <div class="bg-white rounded-xl border border-border p-4">
            <form method="GET" action="{{ route('audit.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                <div>
                    <label for="audit-user" class="block text-xs font-medium text-text-light mb-1.5">Usuario</label>
                    <select id="audit-user" name="user_id" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todos</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="audit-action" class="block text-xs font-medium text-text-light mb-1.5">Acción</label>
                    <select id="audit-action" name="action" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todas</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $actionLabels[$action] ?? $action }}</option>
                        @endforeach
                    </select>
                    @error('action') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="audit-entity" class="block text-xs font-medium text-text-light mb-1.5">Entidad</label>
                    <select id="audit-entity" name="entity_type" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todas</option>
                        @foreach($entityTypes as $entityType)
                            <option value="{{ $entityType }}" @selected(($filters['entity_type'] ?? '') === $entityType)>{{ $entityLabels[strtolower($entityType)] ?? $entityType }}</option>
                        @endforeach
                    </select>
                    @error('entity_type') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="audit-date" class="block text-xs font-medium text-text-light mb-1.5">Fecha</label>
                    <input id="audit-date" name="date" type="date" value="{{ $filters['date'] ?? '' }}" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <p class="mt-1 text-xs text-text-muted">{{ config('app.timezone') }}</p>
                    @error('date') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">Filtrar</button>
                    <a href="{{ route('audit.index') }}" class="text-sm text-primary hover:underline">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Fecha</th>
                            <th class="px-6 py-4">Usuario</th>
                            <th class="px-6 py-4">Acción</th>
                            <th class="px-6 py-4">Entidad</th>
                            <th class="px-6 py-4">ID</th>
                            <th class="px-6 py-4">Detalles</th>
                            <th class="px-6 py-4">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($logs as $log)
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4 text-text whitespace-nowrap">{{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? 'Sin fecha registrada' }}</td>
                                <td class="px-6 py-4 text-text">{{ $log->user?->name ?? 'Sin usuario disponible' }}</td>
                                <td class="px-6 py-4 text-text">{{ $actionLabels[$log->action] ?? $log->action }}</td>
                                <td class="px-6 py-4 text-text">{{ $entityLabels[strtolower($log->entity_type)] ?? $log->entity_type }}</td>
                                <td class="px-6 py-4 font-mono text-text-muted">#{{ $log->entity_id }}</td>
                                <td class="px-6 py-4 text-text-light min-w-64 max-w-lg">
                                    @if($log->details !== null)
                                        <details>
                                            <summary class="cursor-pointer text-primary">Ver detalles</summary>
                                            <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs">{{ json_encode($log->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
                                        </details>
                                    @else
                                        Sin detalles registrados
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-mono text-text-muted">{{ $log->ip_address ?? 'Sin IP registrada' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-text-muted">
                                    {{ collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty()
                                        ? 'No hay registros de auditoría con estos filtros.'
                                        : 'No hay acciones registradas en la auditoría.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :paginator="$logs" />
        </div>
    </div>
</x-app-layout>
