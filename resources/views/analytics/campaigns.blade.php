<x-app-layout>
    <x-slot name="title">Analíticas - Campañas</x-slot>
    <div class="space-y-6">
        <div>
            <a href="{{ route('analytics.index') }}" class="text-sm text-primary hover:underline">Volver a Analíticas</a>
            <h2 class="mt-2 text-2xl font-bold text-text">Analíticas por Campaña</h2>
            <p class="mt-1 text-sm text-text-muted">Campañas guardadas y estadísticas acumuladas registradas</p>
        </div>
        @if(isset($error))
            <div role="alert" class="rounded-xl border border-danger/20 bg-danger/10 p-4 text-danger">{{ $error }}</div>
        @else
            <p class="text-sm text-text-light">Se muestran las estadísticas almacenadas. Publicar o asignar una campaña no confirma reproducciones ni impresiones; una campaña sin mediciones se indica sin estadísticas registradas.</p>
            <div class="bg-white rounded-xl border border-border overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-surface text-xs font-semibold text-text-light uppercase">
                            <tr>
                                <th class="px-4 py-4">Campaña</th>
                                <th class="px-4 py-4">Estado</th>
                                <th class="px-4 py-4">Impresiones registradas</th>
                                <th class="px-4 py-4">Reproducciones registradas</th>
                                <th class="px-4 py-4">Estadísticas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($report['campaigns'] as $campaign)
                                <tr class="hover:bg-surface/50">
                                    <td class="px-4 py-4 font-medium text-text">{{ $campaign['name'] }} <span class="text-xs text-text-muted">#{{ $campaign['id'] }}</span></td>
                                    <td class="px-4 py-4"><x-status-badge :status="$campaign['status']" /></td>
                                    <td class="px-4 py-4 text-text">{{ number_format($campaign['total_impressions']) }}</td>
                                    <td class="px-4 py-4 text-text">{{ number_format($campaign['total_plays']) }}</td>
                                    <td class="px-4 py-4 text-text-light">{{ $campaign['statistics_count'] > 0 ? $campaign['statistics_count'].' registros' : 'Sin estadísticas registradas' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-text-muted">No hay campañas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
