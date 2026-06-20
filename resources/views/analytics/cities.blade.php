<x-app-layout>
    <x-slot name="title">Analíticas - Ciudades</x-slot>

    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Analíticas por Ciudad</h2>
                <p class="mt-1 text-sm text-text-muted">Actividad y visualización por ciudad</p>
            </div>
        </div>

        {{-- Map visualization placeholder --}}
        <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
            <h3 class="text-sm font-semibold text-text mb-4">Mapa de Actividad</h3>
            <div class="aspect-[16/9] bg-surface rounded-lg flex items-center justify-center relative overflow-hidden">
                <div class="absolute inset-0 grid grid-cols-6 grid-rows-4 gap-4 p-8">
                    @php
                    $cityCoords = [
                        ['Bogotá', 45, 55, rand(80, 100)],
                        ['Medellín', 35, 40, rand(70, 95)],
                        ['Cali', 25, 65, rand(60, 90)],
                        ['Barranquilla', 75, 30, rand(50, 85)],
                        ['Cartagena', 80, 35, rand(40, 80)],
                        ['Bucaramanga', 55, 35, rand(45, 75)],
                        ['Pereira', 28, 55, rand(40, 70)],
                        ['Manizales', 30, 50, rand(35, 65)],
                    ];
                    @endphp
                    @foreach($cityCoords as $city)
                        <div class="absolute flex flex-col items-center" style="left: {{ $city[1] }}%; top: {{ $city[2] }}%;">
                            <div class="w-4 h-4 rounded-full border-2 border-white shadow-md flex items-center justify-center"
                                 style="background-color: {{ $city[3] > 80 ? '#10B981' : ($city[3] > 60 ? '#F59E0B' : '#EF4444') }};"></div>
                            <div class="absolute -bottom-5 whitespace-nowrap bg-white px-2 py-0.5 rounded text-xs font-medium shadow-sm border border-border text-text">
                                {{ $city[0] }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="absolute bottom-4 right-4 bg-white rounded-lg border border-border px-3 py-2 shadow-sm">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-success"></span> Alta</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-warning"></span> Media</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-danger"></span> Baja</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Actividad por Ciudad</h3>
                <div class="space-y-3">
                    @php $cities = ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena', 'Bucaramanga', 'Pereira', 'Manizales']; @endphp
                    @foreach($cities as $city)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-24">{{ $city }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-primary rounded" style="width: {{ rand(30, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-12 text-right">{{ rand(100, 5000) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Dispositivos por Ciudad</h3>
                <div class="space-y-3">
                    @foreach($cities as $city)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-24">{{ $city }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-info rounded" style="width: {{ rand(20, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-10 text-right">{{ rand(2, 50) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            <div class="px-6 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Ciudades</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Ciudad</th>
                            <th class="px-6 py-4">Dispositivos</th>
                            <th class="px-6 py-4">Actividad</th>
                            <th class="px-6 py-4">Horas</th>
                            <th class="px-6 py-4">Uptime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($cities as $city)
                            @php
                            $devices = rand(2, 50);
                            $activity = rand(100, 5000);
                            $hours = rand(50, 2000);
                            $uptime = rand(85, 99);
                            @endphp
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4 font-medium text-text">{{ $city }}</td>
                                <td class="px-6 py-4 text-text">{{ $devices }}</td>
                                <td class="px-6 py-4 text-text">{{ number_format($activity) }}</td>
                                <td class="px-6 py-4 text-text">{{ $hours }} h</td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-medium {{ $uptime > 95 ? 'text-success' : 'text-warning' }}">{{ $uptime }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
