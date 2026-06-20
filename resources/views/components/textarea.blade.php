@props([
    'label' => null,
    'name',
    'placeholder' => null,
    'value' => null,
    'error' => null,
    'rows' => 4,
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

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        class="w-full rounded-lg border {{ $error ? 'border-danger' : 'border-border' }} bg-white text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-colors duration-150 px-4 py-2.5 text-sm resize-y"
    >{{ $value }}</textarea>

    @if ($error)
        <p class="mt-1.5 text-xs text-danger">{{ $error }}</p>
    @endif
</div>
