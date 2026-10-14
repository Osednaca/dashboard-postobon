<x-app-layout>
    <x-slot name="title">Detalle de Usuario</x-slot>


    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ auth()->user()->can('viewAny', App\Models\User::class) ? route('users.index') : route('dashboard.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-text">{{ $user->name }}</h2>
                    <p class="mt-1 text-sm text-text-muted">{{ $user->email }}</p>
                </div>
            </div>
            @can('update', $user)
            <div class="flex items-center gap-2">
                <a href="{{ route('users.edit', $user->id) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Editar
                </a>
            </div>
            @endcan
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
                        {{ $user->roleLabel() }}
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

        </div>
    </div>
</x-app-layout>
