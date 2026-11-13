<x-app-layout>
    <x-slot name="title">Analíticas</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Analíticas</h2>
                <p class="mt-1 text-sm text-text-muted">Inventario actual y estadísticas acumuladas registradas en el sistema</p>
            </div>
        </div>

        @if(isset($error))
            <div role="alert" class="rounded-xl border border-danger/20 bg-danger/10 p-4 text-danger">{{ $error }}</div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach([
                    ['Dispositivos', number_format($report['kpis']['device_count']), 'Inventario de modelos 42 y 35'],
                    ['En línea registrados', number_format($report['kpis']['online_count']), $report['kpis']['unknown_count'].' sin estado confirmado'],
                    ['Horas acumuladas registradas', number_format($report['kpis']['working_hours'], 2, ',', '.').' h', 'Con datos: '.$report['kpis']['hours_device_count'].' dispositivos'],
                    ['Campañas', number_format($report['kpis']['total_campaigns']), 'Campañas guardadas en el sistema'],
                ] as [$label, $value, $description])
                    <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
                        <div class="text-sm text-text-muted">{{ $label }}</div>
                        <div class="text-2xl font-bold text-text mt-1">{{ $value }}</div>
                        <div class="text-xs text-text-muted mt-2">{{ $description }}</div>
                    </div>
                @endforeach
            </div>

            <div class="rounded-xl border border-border bg-white p-4 text-sm text-text-light">
                Los estados corresponden al último registro local, no a una consulta en vivo. Las horas son acumuladas y sólo incluyen equipos con ese dato.
                Impresiones registradas: <strong>{{ number_format($report['kpis']['total_impressions']) }}</strong>.
                Reproducciones registradas: <strong>{{ number_format($report['kpis']['total_plays']) }}</strong>.
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="min-w-0">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-text">Dispositivos por Ciudad</h3>
                        <a href="{{ route('analytics.cities') }}" class="text-sm text-primary hover:underline">Ver detalle</a>
                    </div>
                    @include('analytics.inventory-table', ['rows' => $report['cities'], 'labelKey' => 'city', 'label' => 'Ciudad'])
                </section>
                <section class="min-w-0">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-text">Dispositivos por Grupo</h3>
                        <a href="{{ route('analytics.groups') }}" class="text-sm text-primary hover:underline">Ver detalle</a>
                    </div>
                    @include('analytics.inventory-table', ['rows' => $report['groups'], 'labelKey' => 'group', 'label' => 'Grupo'])
                </section>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach(['devices' => 'Dispositivos', 'campaigns' => 'Campañas', 'groups' => 'Grupos', 'cities' => 'Ciudades'] as $page => $label)
                    <a href="{{ route('analytics.'.$page) }}" class="bg-white rounded-xl border border-border p-5 text-sm font-medium text-primary hover:border-primary transition-colors">Analíticas por {{ $label }}</a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
