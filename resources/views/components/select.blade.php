@props([
    'label' => null,
    'name',
    'options' => [],
    'value' => null,
    'error' => null,
    'required' => false,
])

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
        <select
            name="{{ $name }}"
            id="{{ $name }}"
            @if ($required) required @endif
            class="w-full rounded-lg border {{ $error ? 'border-danger' : 'border-border' }} bg-white text-text appearance-none focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-colors duration-150 pl-4 pr-10 py-2.5 text-sm"
        >
            @foreach ($options as $key => $option)
                <option value="{{ $key }}" {{ $value == $key ? 'selected' : '' }}>
                    {{ $option }}
                </option>
            @endforeach
        </select>

        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
    </div>

    @if ($error)
        <p class="mt-1.5 text-xs text-danger">{{ $error }}</p>
    @endif
</div>
