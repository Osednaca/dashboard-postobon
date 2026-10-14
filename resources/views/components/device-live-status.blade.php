@props(['row'])
<span class="inline-flex items-center gap-2" role="status">
    <span class="h-2.5 w-2.5 rounded-full" :class="{ 'bg-success': {{ $row }}.status === 'online', 'bg-warning': {{ $row }}.status === 'powered_off', 'bg-danger': {{ $row }}.status === 'offline', 'bg-text-muted': ['unknown', 'unavailable'].includes({{ $row }}.status) }" aria-hidden="true"></span>
    <span class="text-xs text-text-muted" x-text="operatingLabels[{{ $row }}.status] || 'Consultando estado'">Consultando estado</span>
</span>
