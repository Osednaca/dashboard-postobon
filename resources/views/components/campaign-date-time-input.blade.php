@props(['name', 'value' => null])
@php
    $inputValue = '';
    if ($value instanceof \DateTimeInterface || (is_string($value) && trim($value) !== '')) {
        try {
            $date = \Illuminate\Support\Carbon::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'));
            $inputValue = $date->format($date->second === 0 ? 'Y-m-d\TH:i' : 'Y-m-d\TH:i:s');
        } catch (\Throwable) {
            // Validation errors must remain renderable even when old input is malformed.
        }
    }
@endphp
<input type="datetime-local" step="1" name="{{ $name }}" id="{{ $name }}" value="{{ $inputValue }}"
    {{ $attributes->merge(['class' => 'w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors disabled:opacity-50']) }}>
