<x-app-layout>
    <x-slot name="title">Analíticas - Grupos</x-slot>
    <div class="space-y-6">
        <div>
            <a href="{{ route('analytics.index') }}" class="text-sm text-primary hover:underline">Volver a Analíticas</a>
            <h2 class="mt-2 text-2xl font-bold text-text">Analíticas por Grupo</h2>
            <p class="mt-1 text-sm text-text-muted">Inventario de modelos 42 y 35 por grupo, incluidos los grupos sin dispositivos</p>
        </div>
        @if(isset($error))
            <div role="alert" class="rounded-xl border border-danger/20 bg-danger/10 p-4 text-danger">{{ $error }}</div>
        @else
            <p class="text-sm text-text-light">Estados del último registro local. Las horas acumuladas sólo incluyen dispositivos con ese dato; los equipos sin telemetría se muestran sin confirmar.</p>
            @include('analytics.inventory-table', ['rows' => $report['groups'], 'labelKey' => 'group', 'label' => 'Grupo'])
        @endif
    </div>
</x-app-layout>
