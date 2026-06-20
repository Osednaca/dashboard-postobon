<x-app-layout>
    <x-slot name="title">Nueva Suscripción</x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('subscriptions.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Nueva Suscripción</h2>
                <p class="mt-1 text-sm text-text-muted">Crea una nueva suscripción</p>
            </div>
        </div>

        <form action="{{ route('subscriptions.store') }}" method="POST" class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            @csrf

            <div class="p-6 space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-text mb-1.5">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors" placeholder="Ej. Suscripción Anual 2024">
                    @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-text mb-1.5">Fecha de inicio <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" id="start_date" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-text mb-1.5">Fecha de fin <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" id="end_date" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                    </div>
                </div>

                <div>
                    <label for="alert_days" class="block text-sm font-medium text-text mb-1.5">Días de alerta antes de vencimiento</label>
                    <input type="number" name="alert_days" id="alert_days" min="1" max="90" value="7" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                    <p class="mt-1 text-xs text-text-muted">Se enviará una notificación cuando falten estos días para la expiración.</p>
                </div>

                <div>
                    <label for="restrictions" class="block text-sm font-medium text-text mb-1.5">Restricciones</label>
                    <textarea name="restrictions" id="restrictions" rows="4" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors resize-none" placeholder="Especifica restricciones o limitaciones de esta suscripción..."></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('subscriptions.index') }}" class="px-4 py-2.5 rounded-lg text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">Crear Suscripción</button>
            </div>
        </form>
    </div>
</x-app-layout>
