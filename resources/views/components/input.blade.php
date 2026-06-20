@props([
    'label' => null,
    'name',
    'type' => 'text',
    'placeholder' => null,
    'value' => null,
    'error' => null,
    'icon' => null,
    'required' => false,
])

@php
$iconPaths = [
    'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0',
    'email' => 'M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207',
    'lock' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    'user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    'phone' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
];
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-text mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($icon && isset($iconPaths[$icon]))
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths[$icon] }}"></path>
                </svg>
            </div>
        @endif

        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            @if ($required) required @endif
            class="w-full rounded-lg border {{ $error ? 'border-danger' : 'border-border' }} bg-white text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-colors duration-150
            {{ $icon && isset($iconPaths[$icon]) ? 'pl-10' : 'pl-4' }} pr-4 py-2.5 text-sm"
        />
    </div>

    @if ($error)
        <p class="mt-1.5 text-xs text-danger">{{ $error }}</p>
    @endif
</div>
