@props(['campaign' => null])
@php
    $permanent = (bool) old('is_permanent', $campaign !== null && $campaign->end_date === null);
@endphp
<div x-data="{ permanent: @js($permanent) }">
    <input type="hidden" name="is_permanent" value="0">
    <label class="mb-3 flex items-center gap-2 text-sm font-medium text-text">
        <input type="checkbox" name="is_permanent" value="1" x-model="permanent" @checked($permanent)
            class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
        Campaña permanente
    </label>
    <label for="end_date" class="block text-sm font-medium text-text mb-1.5">Fecha de fin</label>
    <input type="date" name="end_date" id="end_date" :disabled="permanent" :required="!permanent"
        @disabled($permanent) @required(!$permanent)
        value="{{ old('end_date', $campaign?->end_date?->format('Y-m-d') ?? '') }}"
        class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors disabled:opacity-50">
    <p x-show="permanent" class="mt-1 text-xs text-text-light">Sin fecha de fin. Puedes pausarla o finalizarla manualmente.</p>
    @error('end_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    @error('is_permanent')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
</div>
