<x-app-layout>
    <x-slot name="title">Analíticas - Dispositivos</x-slot>
    <div class="space-y-6">
        <div>
            <a href="{{ route('analytics.index') }}" class="text-sm text-primary hover:underline">Volver a Analíticas</a>
            <h2 class="mt-2 text-2xl font-bold text-text">Analíticas por Dispositivo</h2>
            <p class="mt-1 text-sm text-text-muted">Inventario local de modelos 42 y 35 y sus métricas registradas</p>
        </div>
        @if(isset($error))
            <div role="alert" class="rounded-xl border border-danger/20 bg-danger/10 p-4 text-danger">{{ $error }}</div>
        @else
            <p class="text-sm text-text-light">Las horas son acumuladas. El estado corresponde al último registro local, no a una consulta en vivo. No disponible indica que el sistema no almacena esa métrica para el equipo.</p>
            <div class="bg-white rounded-xl border border-border overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-surface text-xs font-semibold text-text-light uppercase">
                            <tr>
                                <th class="px-4 py-4">Dispositivo</th>
                                <th class="px-4 py-4">Modelo</th>
                                <th class="px-4 py-4">Establecimiento / Ciudad</th>
                                <th class="px-4 py-4">Grupo</th>
                                <th class="px-4 py-4">Horas acumuladas</th>
                                <th class="px-4 py-4">RPM registrado</th>
                                <th class="px-4 py-4">Reproducciones registradas</th>
                                <th class="px-4 py-4">Estado registrado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($report['devices'] as $device)
                                <tr class="hover:bg-surface/50">
                                    <td class="px-4 py-4 font-medium text-text">{{ $device['name'] }}<span class="block text-xs text-text-muted">{{ $device['key'] }}</span></td>
                                    <td class="px-4 py-4 text-text">{{ $device['type'] }}</td>
                                    <td class="px-4 py-4 text-text">{{ $device['establishment'] }}<span class="block text-xs text-text-muted">{{ $device['city'] }}</span></td>
                                    <td class="px-4 py-4 text-text">{{ $device['group'] }}</td>
                                    <td class="px-4 py-4 text-text">{{ $device['working_hours'] === null ? 'No disponible' : number_format($device['working_hours'], 2, ',', '.').' h' }}</td>
                                    <td class="px-4 py-4 text-text">{{ $device['rpm'] === null ? 'No disponible' : number_format($device['rpm'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-4 text-text">{{ $device['total_plays'] === null ? 'No disponible' : number_format($device['total_plays']) }}</td>
                                    <td class="px-4 py-4 text-text">{{ ['online' => 'En línea', 'offline' => 'Fuera de línea', 'unknown' => 'Sin confirmar'][$device['status']] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-text-muted">No hay dispositivos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
