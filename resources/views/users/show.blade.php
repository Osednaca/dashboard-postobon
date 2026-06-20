<x-app-layout>
    <x-slot name="title">Detalle de Usuario</x-slot>

    @php
    $user = $user ?? (object)[
        'id' => 1,
        'name' => 'Juan Pérez',
        'email' => 'juan@3dfan.com',
        'role' => 'operator',
        'created_at' => '2024-02-15',
        'updated_at' => '2024-06-10',
    ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('users.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-text">{{ $user->name }}</h2>
                    <p class="mt-1 text-sm text-text-muted">{{ $user->email }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('users.edit', $user->id) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Editar
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Profile Card --}}
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex flex-col items-center text-center">
                    <div class="w-20 h-20 rounded-full bg-primary/10 text-primary flex items-center justify-center text-2xl font-bold">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-text">{{ $user->name }}</h3>
                    <p class="text-sm text-text-muted">{{ $user->email }}</p>
                    <span class="mt-3 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border {{ $user->role === 'admin' ? 'bg-primary/10 text-primary border-primary/20' : 'bg-info/10 text-info border-info/20' }}">
                        {{ $user->role === 'admin' ? 'Administrador' : 'Operador' }}
                    </span>
                </div>
                <div class="mt-6 space-y-3">
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Miembro desde</span>
                        <span class="text-sm font-medium text-text">{{ $user->created_at }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Última actualización</span>
                        <span class="text-sm font-medium text-text">{{ $user->updated_at }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Estado</span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-success">
                            <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                            Activo
                        </span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                {{-- Activity Log --}}
                <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                    <div class="px-6 py-4 border-b border-border">
                        <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Registro de actividad</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Fecha</th>
                                    <th class="px-6 py-3">Acción</th>
                                    <th class="px-6 py-3">Entidad</th>
                                    <th class="px-6 py-3">Detalles</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @php $activities = [
                                    ['date' => '2024-06-10 14:30', 'action' => 'update', 'entity' => 'campaign', 'details' => 'Actualizó campaña "Verano 2024"'],
                                    ['date' => '2024-06-09 10:15', 'action' => 'create', 'entity' => 'schedule', 'details' => 'Creó programación "Apagado nocturno"'],
                                    ['date' => '2024-06-08 16:45', 'action' => 'login', 'entity' => 'user', 'details' => 'Inicio de sesión'],
                                    ['date' => '2024-06-07 09:00', 'action' => 'update', 'entity' => 'device', 'details' => 'Actualizó configuración Fan 3D 012'],
                                ]; @endphp
                                @foreach($activities as $activity)
                                    <tr class="hover:bg-surface/50 transition-colors">
                                        <td class="px-6 py-3 text-sm text-text">{{ $activity['date'] }}</td>
                                        <td class="px-6 py-3">
                                            @php
                                            $actionStyles = ['create' => 'bg-success/10 text-success', 'update' => 'bg-info/10 text-info', 'login' => 'bg-primary/10 text-primary'];
                                            $actionLabels = ['create' => 'Crear', 'update' => 'Actualizar', 'login' => 'Inicio sesión'];
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $actionStyles[$activity['action']] }}">{{ $actionLabels[$activity['action']] }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-sm text-text capitalize">{{ $activity['entity'] }}</td>
                                        <td class="px-6 py-3 text-sm text-text-light">{{ $activity['details'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Campaigns Created --}}
                <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                    <div class="px-6 py-4 border-b border-border">
                        <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Campañas creadas</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Campaña</th>
                                    <th class="px-6 py-3">Estado</th>
                                    <th class="px-6 py-3">Fecha</th>
                                    <th class="px-6 py-3">Reproducciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @php $campaigns = [
                                    ['name' => 'Verano 2024', 'status' => 'active', 'date' => '2024-06-01', 'plays' => 15234],
                                    ['name' => 'Promo Flash', 'status' => 'finished', 'date' => '2024-05-15', 'plays' => 8934],
                                    ['name' => 'Evento Especial', 'status' => 'scheduled', 'date' => '2024-06-20', 'plays' => 0],
                                ]; @endphp
                                @foreach($campaigns as $campaign)
                                    <tr class="hover:bg-surface/50 transition-colors">
                                        <td class="px-6 py-3 font-medium text-text">{{ $campaign['name'] }}</td>
                                        <td class="px-6 py-3">
                                            <x-status-badge :status="$campaign['status']" />
                                        </td>
                                        <td class="px-6 py-3 text-sm text-text">{{ $campaign['date'] }}</td>
                                        <td class="px-6 py-3 text-sm text-text">{{ number_format($campaign['plays']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
