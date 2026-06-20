@props([
    'title' => null,
    'type' => 'line',
    'height' => '300',
])

<div class="bg-white rounded-xl border border-border p-6 shadow-sm">
    @if ($title)
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-text">{{ $title }}</h3>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-primary"></span>
                <span class="text-xs text-text-muted">Datos</span>
            </div>
        </div>
    @endif
    <div class="relative" style="height: {{ $height }}px;">
        <canvas id="chart-{{ $attributes->get('id', uniqid()) }}"></canvas>
    </div>
</div>
