@props([
    'items' => [],
])

<div class="relative">
    <div class="absolute left-4 top-0 bottom-0 w-px bg-border"></div>

    <div class="space-y-6">
        @foreach ($items as $item)
            @php
            $color = $item['color'] ?? 'primary';
            $colorClasses = match($color) {
                'secondary' => 'bg-secondary/10 text-secondary border-secondary/20',
                'accent' => 'bg-accent/10 text-accent border-accent/20',
                'success' => 'bg-success/10 text-success border-success/20',
                'warning' => 'bg-warning/10 text-warning border-warning/20',
                'danger' => 'bg-danger/10 text-danger border-danger/20',
                default => 'bg-primary/10 text-primary border-primary/20',
            };
            @endphp

            <div class="relative flex gap-4">
                {{-- Dot --}}
                <div class="relative z-10 mt-1">
                    <div class="w-8 h-8 rounded-full border-2 bg-white {{ $colorClasses }} flex items-center justify-center">
                        @if (isset($item['icon']))
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                            </svg>
                        @else
                            <div class="w-2.5 h-2.5 rounded-full bg-current"></div>
                        @endif
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 pb-2">
                    <span class="text-xs text-text-muted font-medium">{{ $item['date'] ?? '' }}</span>
                    <h4 class="text-sm font-semibold text-text mt-0.5">{{ $item['title'] ?? '' }}</h4>
                    @if (isset($item['description']))
                        <p class="text-sm text-text-muted mt-1">{{ $item['description'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
