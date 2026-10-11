<x-app-layout>
    <x-slot name="title">Detalle de Campaña</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('campaigns.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-text">{{ $campaign->name }}</h2>
                        <x-campaign-status-badge :status="$campaign->status" />
                    </div>
                    <p class="mt-1 text-sm text-text-muted">{{ $campaign->description }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('campaigns.edit', $campaign) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Editar
                </a>
                @if($campaign->status == 'active')
                    <form method="POST" action="{{ route('campaigns.pause', $campaign) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-warning bg-warning/10 hover:bg-warning/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Pausar
                        </button>
                    </form>
                @elseif($campaign->status == 'paused')
                    <form method="POST" action="{{ route('campaigns.activate', $campaign) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-success bg-success/10 hover:bg-success/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Activar
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @php
            $totalPlays = $campaign->statistics->sum('plays');
            $totalImpressions = $campaign->statistics->sum('impressions');
            $totalDuration = $campaign->statistics->sum('duration');
        @endphp

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm text-text-muted">Reproducciones</div>
                        <div class="text-xl font-bold text-text">{{ number_format($totalPlays) }}</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-secondary/10 text-secondary flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm text-text-muted">Impresiones</div>
                        <div class="text-xl font-bold text-text">{{ number_format($totalImpressions) }}</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-accent/10 text-accent flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm text-text-muted">Duración total</div>
                        <div class="text-xl font-bold text-text">{{ number_format($totalDuration) }} min</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]" x-data="{ tab: 'details' }">
            <div class="border-b border-border">
                <nav class="flex -mb-px overflow-x-auto px-6 pt-4 [&>button]:shrink-0">
                    <button @click="tab = 'details'" :class="tab === 'details' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Detalles</button>
                    <button @click="tab = 'videos'" :class="tab === 'videos' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Videos</button>
                    <button @click="tab = 'devices'" :class="tab === 'devices' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Dispositivos</button>
                    <button @click="tab = 'stats'" :class="tab === 'stats' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Estadísticas</button>
                    <button @click="tab = 'timeline'" :class="tab === 'timeline' ? 'border-primary text-primary' : 'border-transparent text-text-light hover:text-text hover:border-border'" class="mr-8 py-4 px-1 border-b-2 font-medium text-sm transition-colors">Programación</button>
                </nav>
            </div>

            <div class="p-6">
                {{-- Details --}}
                <div x-show="tab === 'details'" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Nombre</span>
                            <span class="text-sm font-medium text-text">{{ $campaign->name }}</span>
                        </div>
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Estado</span>
                            <x-campaign-status-badge :status="$campaign->status" />
                        </div>
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Prioridad</span>
                            <span class="text-sm font-medium text-text">{{ $campaign->priority }}/10</span>
                        </div>
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Fecha inicio</span>
                            <span class="text-sm font-medium text-text">{{ $campaign->start_date ? $campaign->start_date->format('Y-m-d') : '-' }}</span>
                        </div>
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Fecha fin</span>
                            <span class="text-sm font-medium text-text">{{ $campaign->end_date ? $campaign->end_date->format('Y-m-d') : 'Permanente · Sin fecha de fin' }}</span>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Ciudades</span>
                            <div class="flex flex-wrap gap-1 justify-end">
                                @foreach($campaign->segment_cities ?? [] as $city)
                                    <span class="px-2 py-0.5 rounded text-xs bg-surface-dark text-text-light">{{ $city }}</span>
                                @endforeach
                                @if(empty($campaign->segment_cities))
                                    <span class="text-sm text-text-muted">Sin ciudades</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Grupos</span>
                            <div class="flex flex-wrap gap-1 justify-end">
                                @foreach($campaign->segment_groups ?? [] as $groupId)
                                    @php $group = \App\Models\Group::find($groupId); @endphp
                                    @if($group)
                                        <span class="px-2 py-0.5 rounded text-xs bg-surface-dark text-text-light">{{ $group->name }}</span>
                                    @endif
                                @endforeach
                                @if(empty($campaign->segment_groups))
                                    <span class="text-sm text-text-muted">Sin grupos</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Creada</span>
                            <span class="text-sm font-medium text-text">{{ $campaign->created_at ? $campaign->created_at->format('Y-m-d H:i') : '-' }}</span>
                        </div>
                        <div class="flex justify-between py-3 border-b border-border">
                            <span class="text-sm text-text-muted">Última actualización</span>
                            <span class="text-sm font-medium text-text">{{ $campaign->updated_at ? $campaign->updated_at->format('Y-m-d H:i') : '-' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Videos --}}
                <div x-show="tab === 'videos'" x-transition class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <p class="col-span-full text-sm text-text-light">Vista previa del contenido configurado para esta campaña. La reproducción actual se consulta en el detalle de cada dispositivo.</p>
                    @forelse($campaign->media as $mediaItem)
                        <div class="rounded-lg border border-border overflow-hidden hover:border-primary/50 transition-colors">
                            <x-media-preview :media="$mediaItem" detail />
                            <div class="p-3">
                                <div class="text-sm font-medium text-text">{{ $mediaItem->name }}</div>
                                <div class="text-xs text-text-muted mt-0.5"><x-media-duration :media="$mediaItem" /> · Orden #{{ $mediaItem->pivot->order ?? '-' }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-8">
                            <p class="text-sm text-text-light">No hay videos asignados a esta campaña</p>
                        </div>
                    @endforelse
                </div>

                {{-- Devices --}}
                <div x-show="tab === 'devices'" x-transition>
                    <x-campaign-recipients :campaign="$campaign" />
                </div>

                {{-- Stats --}}
                <div x-show="tab === 'stats'" x-transition>
                    @php
                        $statsByCity = \App\Models\CampaignStatistic::select('locations.city', \Illuminate\Support\Facades\DB::raw('SUM(campaign_statistics.plays) as total_plays'), \Illuminate\Support\Facades\DB::raw('SUM(campaign_statistics.impressions) as total_impressions'))
                            ->join('devices', 'campaign_statistics.device_id', '=', 'devices.id')
                            ->join('locations', 'devices.location_id', '=', 'locations.id')
                            ->where('campaign_statistics.campaign_id', $campaign->id)
                            ->groupBy('locations.city')
                            ->get();
                    @endphp
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="rounded-lg border border-border p-4">
                            <h4 class="text-sm font-semibold text-text mb-4">Reproducciones por día</h4>
                            @php
                                $statsByDay = \App\Models\CampaignStatistic::select('date', \Illuminate\Support\Facades\DB::raw('SUM(plays) as total_plays'))
                                    ->where('campaign_id', $campaign->id)
                                    ->groupBy('date')
                                    ->orderByDesc('date')
                                    ->limit(7)
                                    ->get()
                                    ->sortBy('date')
                                    ->values();
                                $maxDayPlays = $statsByDay->max('total_plays') ?: 1;
                            @endphp
                            <div class="space-y-3">
                                @forelse($statsByDay as $dayStat)
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-text-muted w-16">{{ $dayStat->date->format('d/m') }}</span>
                                        <div class="flex-1 h-6 bg-surface-dark rounded overflow-hidden">
                                            <div class="h-full bg-primary rounded" style="width: {{ min(($dayStat->total_plays / $maxDayPlays) * 100, 100) }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-text w-12 text-right">{{ number_format($dayStat->total_plays) }}</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-text-light text-center py-4">No hay datos estadísticos disponibles</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="rounded-lg border border-border p-4">
                            <h4 class="text-sm font-semibold text-text mb-4">Impresiones por ciudad</h4>
                            @php
                                $maxCityImpressions = $statsByCity->max('total_impressions') ?: 1;
                            @endphp
                            <div class="space-y-3">
                                @forelse($statsByCity as $cityStat)
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-text-muted w-24">{{ $cityStat->city ?? 'Sin ciudad' }}</span>
                                        <div class="flex-1 h-6 bg-surface-dark rounded overflow-hidden">
                                            <div class="h-full bg-secondary rounded" style="width: {{ min(($cityStat->total_impressions / $maxCityImpressions) * 100, 100) }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-text w-16 text-right">{{ number_format($cityStat->total_impressions) }}</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-text-light text-center py-4">No hay datos por ciudad</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Timeline --}}
                <div x-show="tab === 'timeline'" x-transition>
                    @php
                        $events = collect();
                        if ($campaign->created_at) {
                            $events->push([
                                'date' => $campaign->created_at,
                                'status' => 'draft',
                                'label' => 'Campaña creada',
                                'desc' => 'Creada por ' . ($campaign->creator?->name ?? 'Administrador') . ' con prioridad ' . $campaign->priority,
                            ]);
                        }
                        if ($campaign->status !== 'draft' && $campaign->updated_at) {
                            $events->push([
                                'date' => $campaign->updated_at,
                                'status' => $campaign->status,
                                'label' => 'Campaña ' . match($campaign->status) {
                                    'active' => 'activada',
                                    'paused' => 'pausada',
                                    'scheduled' => 'programada',
                                    'finished' => 'finalizada',
                                    default => 'actualizada',
                                },
                                'desc' => 'Estado cambiado a ' . match($campaign->status) {
                                    'active' => 'Activa',
                                    'paused' => 'Pausada',
                                    'scheduled' => 'Programada',
                                    'finished' => 'Finalizada',
                                    default => $campaign->status,
                                },
                            ]);
                        }
                        $events = $events->sortByDesc('date')->values();
                    @endphp
                    <div class="space-y-6">
                        @forelse($events as $event)
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center">
                                    <div class="w-3 h-3 rounded-full border-2 bg-white
                                        @if($event['status'] == 'active') border-success
                                        @elseif($event['status'] == 'scheduled') border-info
                                        @elseif($event['status'] == 'paused') border-warning
                                        @elseif($event['status'] == 'finished') border-primary
                                        @else border-text-muted
                                        @endif"></div>
                                    @if(!$loop->last)<div class="w-0.5 flex-1 bg-border mt-2"></div>@endif
                                </div>
                                <div class="pb-6">
                                    <div class="text-xs text-text-muted">{{ $event['date']->format('Y-m-d H:i') }}</div>
                                    <div class="text-sm font-medium text-text mt-1">{{ $event['label'] }}</div>
                                    <div class="text-sm text-text-light mt-0.5">{{ $event['desc'] }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-text-light text-center py-4">No hay eventos registrados</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
