<x-app-layout>
    <x-slot name="title">Auditoría</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Auditoría</h2>
                <p class="mt-1 text-sm text-text-muted">Registro de todas las acciones del sistema</p>
            </div>
            <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Exportar
            </button>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl border border-border p-4">
            <form class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-text-light mb-1.5">Usuario</label>
                    <select class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todos</option>
                        <option value="1">Administrador</option>
                        <option value="2">Operador 1</option>
                        <option value="3">Operador 2</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-light mb-1.5">Acción</label>
                    <select class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todas</option>
                        <option value="create">Crear</option>
                        <option value="update">Actualizar</option>
                        <option value="delete">Eliminar</option>
                        <option value="login">Inicio sesión</option>
                        <option value="logout">Cierre sesión</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-light mb-1.5">Entidad</label>
                    <select class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todas</option>
                        <option value="campaign">Campaña</option>
                        <option value="device">Dispositivo</option>
                        <option value="user">Usuario</option>
                        <option value="schedule">Programación</option>
                        <option value="subscription">Suscripción</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-light mb-1.5">Fecha</label>
                    <input type="date" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                </div>
                <div class="flex items-end">
                    <button type="button" class="w-full px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">Filtrar</button>
                </div>
            </form>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
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
                        @forelse($audits ?? [] as $audit)
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4 text-sm text-text">{{ $audit->created_at ?? '2024-06-10 14:30:00' }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-semibold">
                                            {{ substr($audit->user_name ?? 'A', 0, 1) }}
                                        </div>
                                        <span class="text-sm text-text">{{ $audit->user_name ?? 'Administrador' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                    $actionStyles = [
                                        'create' => 'bg-success/10 text-success border-success/20',
                                        'update' => 'bg-info/10 text-info border-info/20',
                                        'delete' => 'bg-danger/10 text-danger border-danger/20',
                                        'login' => 'bg-primary/10 text-primary border-primary/20',
                                        'logout' => 'bg-text-muted/10 text-text-muted border-text-muted/20',
                                    ];
                                    $actionLabels = ['create' => 'Crear', 'update' => 'Actualizar', 'delete' => 'Eliminar', 'login' => 'Inicio sesión', 'logout' => 'Cierre sesión'];
                                    $action = $audit->action ?? 'create';
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $actionStyles[$action] ?? 'bg-gray-100 text-gray-700 border-gray-200' }}">
                                        {{ $actionLabels[$action] ?? ucfirst($action) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-text capitalize">{{ $audit->entity ?? 'Campaign' }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-text-muted">#{{ $audit->entity_id ?? 1 }}</td>
                                <td class="px-6 py-4 text-sm text-text-light max-w-xs truncate">{{ $audit->details ?? 'Campaña "Verano 2024" creada con 3 videos' }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-text-muted">{{ $audit->ip ?? '192.168.1.1' }}</td>
                            </tr>
                        @empty
                            @php $sampleAudits = [
                                ['date' => '2024-06-10 14:30:00', 'user' => 'Administrador', 'action' => 'create', 'entity' => 'campaign', 'id' => 12, 'details' => 'Campaña "Verano 2024" creada', 'ip' => '192.168.1.10'],
                                ['date' => '2024-06-10 14:25:00', 'user' => 'Operador 1', 'action' => 'update', 'entity' => 'device', 'id' => 45, 'details' => 'Configuración actualizada', 'ip' => '192.168.1.15'],
                                ['date' => '2024-06-10 13:00:00', 'user' => 'Administrador', 'action' => 'delete', 'entity' => 'schedule', 'id' => 8, 'details' => 'Programación eliminada', 'ip' => '192.168.1.10'],
                                ['date' => '2024-06-10 08:30:00', 'user' => 'Operador 2', 'action' => 'login', 'entity' => 'user', 'id' => 3, 'details' => 'Inicio de sesión exitoso', 'ip' => '192.168.1.20'],
                                ['date' => '2024-06-09 18:00:00', 'user' => 'Operador 2', 'action' => 'logout', 'entity' => 'user', 'id' => 3, 'details' => 'Cierre de sesión', 'ip' => '192.168.1.20'],
                            ]; @endphp
                            @foreach($sampleAudits as $audit)
                                <tr class="hover:bg-surface/50 transition-colors">
                                    <td class="px-6 py-4 text-sm text-text">{{ $audit['date'] }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-semibold">{{ substr($audit['user'], 0, 1) }}</div>
                                            <span class="text-sm text-text">{{ $audit['user'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                        $actionStyles = ['create' => 'bg-success/10 text-success border-success/20', 'update' => 'bg-info/10 text-info border-info/20', 'delete' => 'bg-danger/10 text-danger border-danger/20', 'login' => 'bg-primary/10 text-primary border-primary/20', 'logout' => 'bg-text-muted/10 text-text-muted border-text-muted/20'];
                                        $actionLabels = ['create' => 'Crear', 'update' => 'Actualizar', 'delete' => 'Eliminar', 'login' => 'Inicio sesión', 'logout' => 'Cierre sesión'];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $actionStyles[$audit['action']] }}">{{ $actionLabels[$audit['action']] }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-text capitalize">{{ $audit['entity'] }}</td>
                                    <td class="px-6 py-4 text-sm font-mono text-text-muted">#{{ $audit['id'] }}</td>
                                    <td class="px-6 py-4 text-sm text-text-light max-w-xs truncate">{{ $audit['details'] }}</td>
                                    <td class="px-6 py-4 text-sm font-mono text-text-muted">{{ $audit['ip'] }}</td>
                                </tr>
                            @endforeach
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(isset($audits) && $audits->hasPages())
                <div class="border-t border-border">
                    <x-pagination :paginator="$audits" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
