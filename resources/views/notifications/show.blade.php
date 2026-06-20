<x-app-layout>
    <x-slot name="title">Detalle de Notificación</x-slot>

    @php
    $notification = $notification ?? (object)[
        'id' => 1,
        'title' => 'Campaña finalizada',
        'message' => 'La campaña "Verano 2024" ha finalizado correctamente. Se han registrado un total de 15,234 reproducciones y 89,345 impresiones durante el período de ejecución.',
        'type' => 'system',
        'read_at' => null,
        'created_at' => '2024-06-10 14:30:00',
        'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'color' => 'text-info bg-info/10',
    ];
    @endphp

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('notifications.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h2 class="text-2xl font-bold text-text">Detalle de Notificación</h2>
        </div>

        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl {{ $notification->color }} flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $notification->icon }}"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold text-text">{{ $notification->title }}</h3>
                            @if(!$notification->read_at)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary border border-primary/20">Nueva</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-1 text-sm text-text-muted">
                            <span>{{ $notification->created_at }}</span>
                            <span>·</span>
                            <span class="capitalize">{{ $notification->type }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-surface rounded-lg">
                    <p class="text-sm text-text leading-relaxed">{{ $notification->message }}</p>
                </div>

                @if(!$notification->read_at)
                    <div class="mt-6 flex items-center gap-3">
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-primary hover:bg-primary/90 transition-colors shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Marcar como leída
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
