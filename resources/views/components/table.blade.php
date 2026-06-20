@props([
    'headers' => [],
    'striped' => true,
    'hover' => true,
    'compact' => false,
])

@php
$cellClasses = $compact ? 'px-4 py-2' : 'px-6 py-4';
@endphp

<div class="bg-white rounded-xl border border-border overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            @if (isset($thead))
                <thead>
                    {{ $thead }}
                </thead>
            @elseif (!empty($headers))
                <thead class="bg-surface border-b border-border">
                    <tr>
                        @foreach ($headers as $header)
                            <th class="{{ $cellClasses }} text-xs font-semibold text-text-muted uppercase tracking-wider">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody class="divide-y divide-border">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if (isset($pagination))
        <div class="px-6 py-3 border-t border-border bg-surface/50">
            {{ $pagination }}
        </div>
    @endif
</div>
