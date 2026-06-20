<x-app-layout>
    <x-slot name="title">Usuarios</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Usuarios</h2>
                <p class="mt-1 text-sm text-text-muted">Gestiona los usuarios del sistema</p>
            </div>
            <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nuevo Usuario
            </a>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl border border-border p-4">
            <div class="flex items-center gap-4">
                <div class="flex-1 flex items-center gap-3">
                    <svg class="w-5 h-5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" placeholder="Buscar usuarios..." class="w-full text-sm outline-none placeholder:text-text-muted">
                </div>
                <select class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="">Todos los roles</option>
                    <option value="admin">Administrador</option>
                    <option value="operator">Operador</option>
                </select>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Nombre</th>
                            <th class="px-6 py-4">Email</th>
                            <th class="px-6 py-4">Rol</th>
                            <th class="px-6 py-4">Fecha Creación</th>
                            <th class="px-6 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($users ?? [] as $user)
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-semibold">
                                            {{ substr($user->name ?? 'U', 0, 1) }}
                                        </div>
                                        <span class="font-medium text-text">{{ $user->name ?? 'Usuario' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-text">{{ $user->email ?? 'user@example.com' }}</td>
                                <td class="px-6 py-4">
                                    @php $role = $user->role ?? 'admin'; @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border
                                        {{ $role === 'admin' ? 'bg-primary/10 text-primary border-primary/20' : 'bg-info/10 text-info border-info/20' }}">
                                        {{ $role === 'admin' ? 'Administrador' : 'Operador' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-text">{{ $user->created_at ?? '2024-01-15' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-action-button href="{{ route('users.show', $user->id ?? 1) }}" icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" label="Ver" />
                                        <x-action-button href="{{ route('users.edit', $user->id ?? 1) }}" icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" label="Editar" color="primary" />
                                        @if(($user->role ?? 'admin') !== 'admin' || true)
                                            <x-action-button href="{{ route('users.destroy', $user->id ?? 1) }}" icon="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" label="Eliminar" color="danger" method="DELETE" confirm="true" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            @php $sampleUsers = [
                                ['name' => 'Administrador', 'email' => 'admin@3dfan.com', 'role' => 'admin', 'created_at' => '2024-01-01'],
                                ['name' => 'Juan Pérez', 'email' => 'juan@3dfan.com', 'role' => 'operator', 'created_at' => '2024-02-15'],
                                ['name' => 'María García', 'email' => 'maria@3dfan.com', 'role' => 'operator', 'created_at' => '2024-03-10'],
                                ['name' => 'Carlos López', 'email' => 'carlos@3dfan.com', 'role' => 'operator', 'created_at' => '2024-04-05'],
                            ]; @endphp
                            @foreach($sampleUsers as $user)
                                <tr class="hover:bg-surface/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-semibold">{{ substr($user['name'], 0, 1) }}</div>
                                            <span class="font-medium text-text">{{ $user['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-text">{{ $user['email'] }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $user['role'] === 'admin' ? 'bg-primary/10 text-primary border-primary/20' : 'bg-info/10 text-info border-info/20' }}">
                                            {{ $user['role'] === 'admin' ? 'Administrador' : 'Operador' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-text">{{ $user['created_at'] }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-action-button href="{{ route('users.show', 1) }}" icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" label="Ver" />
                                            <x-action-button href="{{ route('users.edit', 1) }}" icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" label="Editar" color="primary" />
                                            <x-action-button href="{{ route('users.destroy', 1) }}" icon="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" label="Eliminar" color="danger" method="DELETE" confirm="true" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(isset($users) && $users->hasPages())
                <div class="border-t border-border">
                    <x-pagination :paginator="$users" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
