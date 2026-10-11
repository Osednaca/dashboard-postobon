@props(['campaign'])
<div class="space-y-3">
    <p class="text-sm text-text-muted">{{ $campaign->target_devices === null ? 'Asignación histórica de dispositivos Z2.' : 'Destinatarios seleccionados individualmente.' }} Esta configuración no confirma la reproducción actual.</p>
    <div class="grid gap-3 sm:grid-cols-2">
        @forelse(app(\App\Services\CampaignTargetCatalog::class)->forCampaign($campaign) as $recipient)
            <div class="rounded-lg border border-border p-3">
                @if($recipient['detail_url'])<a href="{{ $recipient['detail_url'] }}" class="font-medium text-primary hover:underline">{{ $recipient['name'] }}</a>
                @else<span class="font-medium text-danger">{{ $recipient['name'] }}</span>@endif
                <p class="text-xs text-text-muted">{{ strtoupper($recipient['type']) }} · {{ $recipient['city'] ?: 'Sin ciudad' }} · {{ $recipient['group_name'] ?: 'Sin grupo' }}</p>
            </div>
        @empty
            <p class="text-sm text-text-muted">No hay dispositivos asignados a esta campaña.</p>
        @endforelse
    </div>
</div>
