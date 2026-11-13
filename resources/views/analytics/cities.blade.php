<x-app-layout>
    <x-slot name="title">Analíticas - Ciudades</x-slot>
    <div class="space-y-6">
        <div>
            <a href="{{ route('analytics.index') }}" class="text-sm text-primary hover:underline">Volver a Analíticas</a>
            <h2 class="mt-2 text-2xl font-bold text-text">Analíticas por Ciudad</h2>
            <p class="mt-1 text-sm text-text-muted">Inventario de modelos 42 y 35 por ciudad registrada</p>
        </div>
        @if(isset($error))
            <div role="alert" class="rounded-xl border border-danger/20 bg-danger/10 p-4 text-danger">{{ $error }}</div>
        @else
            <p class="text-sm text-text-light">Se usa la ciudad del dispositivo o su ubicación histórica. Los equipos sin ciudad se incluyen como Sin ciudad. Estados del último registro local y horas acumuladas disponibles.</p>
            @include('analytics.inventory-table', ['rows' => $report['cities'], 'labelKey' => 'city', 'label' => 'Ciudad'])
        @endif
    </div>
</x-app-layout>
