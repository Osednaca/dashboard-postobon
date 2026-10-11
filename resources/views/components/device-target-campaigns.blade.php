@props(['targetKey', 'legacyDeviceId' => null])
<div class="space-y-3">
    @forelse(\App\Models\Campaign::forTarget($targetKey, $legacyDeviceId)->latest()->limit(5)->get() as $assigned)
        <div class="rounded-lg border border-border p-3">
            <div class="flex items-center justify-between gap-2"><a href="{{ route('campaigns.show', $assigned) }}" class="font-semibold text-primary hover:underline">{{ $assigned->name }}</a><x-campaign-status-badge :status="$assigned->status" /></div>
            <p class="mt-2 text-sm text-text-muted">{{ $assigned->description ?: 'Sin descripción' }}</p>
            <p class="mt-1 text-xs text-text-muted">Destinatario configurado; no confirma reproducción actual.</p>
        </div>
    @empty
        <p class="text-sm text-text-muted">No hay campañas asignadas a este dispositivo.</p>
    @endforelse
</div>
