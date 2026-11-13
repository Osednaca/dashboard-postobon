<div class="bg-white rounded-xl border border-border overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-surface text-xs font-semibold text-text-light uppercase">
                <tr>
                    <th class="px-4 py-4">{{ $label }}</th>
                    <th class="px-4 py-4">Dispositivos</th>
                    <th class="px-4 py-4">En línea registrados</th>
                    <th class="px-4 py-4">Fuera de línea registrados</th>
                    <th class="px-4 py-4">Sin confirmar</th>
                    <th class="px-4 py-4">Horas acumuladas registradas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($rows as $row)
                    <tr class="hover:bg-surface/50">
                        <td class="px-4 py-4 font-medium text-text">
                            {{ $row[$labelKey] }}
                            @if($labelKey === 'group' && $row['id'] !== null)
                                <span class="text-xs text-text-muted">#{{ $row['id'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-text">{{ number_format($row['device_count']) }}</td>
                        <td class="px-4 py-4 text-text">{{ number_format($row['online_count']) }}</td>
                        <td class="px-4 py-4 text-text">{{ number_format($row['offline_count']) }}</td>
                        <td class="px-4 py-4 text-text">{{ number_format($row['unknown_count']) }}</td>
                        <td class="px-4 py-4 text-text">
                            {{ $row['hours_device_count'] > 0 ? number_format($row['working_hours'], 2, ',', '.').' h' : 'Sin datos' }}
                            <span class="block text-xs text-text-muted">Con datos: {{ $row['hours_device_count'] }} dispositivos</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-text-muted">No hay datos registrados para esta analítica.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
