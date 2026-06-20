@props([
    'tabs' => [],
])

<div x-data="{ activeTab: '{{ collect($tabs)->first(fn($t) => $t['active'] ?? false, collect($tabs)->first())['id'] ?? '' }}' }">
    {{-- Tab buttons --}}
    <div class="flex items-center gap-1 border-b border-border mb-6 overflow-x-auto">
        @foreach ($tabs as $tab)
            <button
                @click="activeTab = '{{ $tab['id'] }}'"
                :class="activeTab === '{{ $tab['id'] }}' ? 'border-b-2 border-primary text-primary font-medium' : 'text-text-muted hover:text-text hover:bg-surface'"
                class="px-4 py-2.5 text-sm rounded-t-lg transition-all duration-150 whitespace-nowrap"
            >
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Tab content --}}
    <div>
        {{ $slot }}
    </div>
</div>
