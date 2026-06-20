<x-app-layout>
    <x-slot name="title">Notificaciones</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Notificaciones</h2>
                <p class="mt-1 text-sm text-text-muted">Centro de notificaciones del sistema</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('notifications.read-all') }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Marcar todas como leídas
                    </button>
                </form>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]" x-data="{ tab: 'all' }">
            <div class="border-b border-border">
                <nav class="flex -mb-px px-6 pt-4">
                    <button @click="tab = 'all'" :class="tab === 'all' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Todas</button>
                    <button @click="tab = 'unread'" :class="tab === 'unread' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2">
                        No Leídas
                        <span class="px-1.5 py-0.5 rounded-full bg-primary text-white text-xs">3</span>
                    </button>
                    <button @click="tab = 'system'" :class="tab === 'system' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Sistema</button>
                    <button @click="tab = 'email'" :class="tab === 'email' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Email</button>
                </nav>
            </div>

            <div class="divide-y divide-border">
                @php
                $notifications = [
                    ['id' => 1, 'type' => 'system', 'read' => false, 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Campaña finalizada', 'message' => 'La campaña "Verano 2024" ha finalizado correctamente.', 'time' => 'Hace 5 minutos', 'color' => 'text-info bg-info/10'],
                    ['id' => 2, 'type' => 'email', 'read' => false, 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'title' => 'Reporte semanal', 'message' => 'Tu reporte semanal de analíticas está listo.', 'time' => 'Hace 2 horas', 'color' => 'text-primary bg-primary/10'],
                    ['id' => 3, 'type' => 'system', 'read' => false, 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z', 'title' => 'Alerta de suscripción', 'message' => 'Tu suscripción expira en 7 días. Renueva para evitar interrupciones.', 'time' => 'Hace 1 día', 'color' => 'text-warning bg-warning/10'],
                    ['id' => 4, 'type' => 'system', 'read' => true, 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Dispositivo conectado', 'message' => 'Fan 3D 045 se ha conectado correctamente.', 'time' => 'Hace 3 días', 'color' => 'text-success bg-success/10'],
                    ['id' => 5, 'type' => 'email', 'read' => true, 'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', 'title' => 'Nuevo usuario registrado', 'message' => 'El usuario Juan Pérez ha sido registrado en el sistema.', 'time' => 'Hace 5 días', 'color' => 'text-secondary bg-secondary/10'],
                ];
                @endphp

                @forelse($notifications as $notification)
                    @if(request()->route('tab') ?? true)
                        <div class="flex items-start gap-4 px-6 py-4 hover:bg-surface/50 transition-colors {{ $notification['read'] ? '' : 'bg-primary/5' }}"
                             x-show="tab === 'all' || tab === '{{ $notification['type'] }}' || (tab === 'unread' && {{ $notification['read'] ? 'false' : 'true' }})"
                             x-transition>
                            <div class="w-10 h-10 rounded-lg {{ $notification['color'] }} flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $notification['icon'] }}"></path>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-medium text-text">{{ $notification['title'] }}</h3>
                                    @if(!$notification['read'])
                                        <span class="w-2 h-2 rounded-full bg-primary"></span>
                                    @endif
                                </div>
                                <p class="text-sm text-text-light mt-0.5">{{ $notification['message'] }}</p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs text-text-muted">{{ $notification['time'] }}</span>
                                    <span class="text-xs text-text-muted capitalize">{{ $notification['type'] }}</span>
                                    @if(!$notification['read'])
                                        <form method="POST" action="{{ route('notifications.read', $notification['id']) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-primary hover:underline">Marcar como leída</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('notifications.show', $notification['id']) }}" class="text-xs text-primary hover:underline">Ver detalle</a>
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-surface-dark flex items-center justify-center">
                                <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                            </div>
                            <p class="text-text-muted">No hay notificaciones</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
