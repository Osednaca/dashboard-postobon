<x-app-layout>
    <x-slot name="title">Editar Suscripción</x-slot>

    @php $subscription = $subscription ?? (object)['id' => 1, 'name' => 'Suscripción Anual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'alert_days' => 7, 'restrictions' => 'Máximo 10 dispositivos activos']; @endphp

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('subscriptions.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Editar Suscripción</h2>
                <p class="mt-1 text-sm text-text-muted">{{ $subscription->name }}</p>
            </div>
        </div>

        <form action="{{ route('subscriptions.update', $subscription->id) }}" method="POST" class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            @csrf
            @method('PUT')

            <div class="p-6 space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-text mb-1.5">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" required value="{{ old('name', $subscription->name) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-text mb-1.5">Fecha de inicio</label>
                        <input type="date" name="start_date" id="start_date" value="{{ old('start_date', $subscription->start_date) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-text mb-1.5">Fecha de fin</label>
                        <input type="date" name="end_date" id="end_date" value="{{ old('end_date', $subscription->end_date) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                    </div>
                </div>

                <div>
                    <label for="alert_days" class="block text-sm font-medium text-text mb-1.5">Días de alerta</label>
                    <input type="number" name="alert_days" id="alert_days" min="1" max="90" value="{{ old('alert_days', $subscription->alert_days) }}" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                </div>

                <div>
                    <label for="restrictions" class="block text-sm font-medium text-text mb-1.5">Restricciones</label>
                    <textarea name="restrictions" id="restrictions" rows="4" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors resize-none">{{ old('restrictions', $subscription->restrictions) }}</textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('subscriptions.index') }}" class="px-4 py-2.5 rounded-lg text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">Guardar Cambios</button>
            </div>
        </form>
    </div>
</x-app-layout>
