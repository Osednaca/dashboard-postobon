@props([
    'trigger',
    'items' => [],
])

<div class="relative" x-data="{ open: false }">
    <div @click="open = !open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-border z-50 overflow-hidden"
    >
        <div class="py-1">
            @foreach ($items as $item)
                @if (isset($item['divider']) && $item['divider'])
                    <div class="border-t border-border my-1"></div>
                @else
                    <a
                        href="{{ $item['href'] ?? '#' }}"
                        @if (isset($item['onclick'])) onclick="{{ $item['onclick'] }}" @endif
                        class="block px-4 py-2 text-sm text-text hover:bg-surface {{ isset($item['danger']) && $item['danger'] ? 'text-danger' : '' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
