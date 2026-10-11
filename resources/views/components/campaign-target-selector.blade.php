@props(['campaign' => null])
@php
    $catalogService = app(\App\Services\CampaignTargetCatalog::class);
    $catalog = $catalogService->all()->values()->all();
    $initial = old('target_devices', $campaign ? $catalogService->forCampaign($campaign)->pluck('key')->all() : []);
@endphp
<div x-data="campaignTargetSelector(@js($catalog), @js($initial))" class="space-y-4" data-campaign-target-selector>
    <p class="text-sm text-text-muted">Selecciona equipos individuales de una o varias ciudades. Los filtros sólo cambian los equipos visibles; conservan tu selección y no agregan otros destinatarios.</p>
    <input type="hidden" name="target_selection_present" value="1">
    <template x-for="key in selected" :key="key"><input type="hidden" name="target_devices[]" :value="key"></template>
    @error('target_devices')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    @foreach($errors->get('target_devices.*') as $messages)
        @foreach($messages as $message)<p class="text-sm text-danger" role="alert">{{ $message }}</p>@endforeach
    @endforeach
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm text-text">Ciudad
            <select x-model="city" class="mt-1 w-full rounded-lg border border-border bg-white p-2">
                <option value="">Todas las ciudades</option><option value="__none__">Sin ciudad</option>
                <template x-for="name in cities" :key="name"><option :value="name" x-text="name"></option></template>
            </select>
        </label>
        <label class="text-sm text-text">Grupo
            <select x-model="group" class="mt-1 w-full rounded-lg border border-border bg-white p-2">
                <option value="">Todos los grupos</option>
                <template x-for="item in groups" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
            </select>
        </label>
    </div>
    <section aria-label="Dispositivos seleccionados" class="rounded-lg border border-border bg-surface p-3">
        <h3 class="text-sm font-semibold text-text">Destinatarios (<span x-text="selected.length">0</span>)</h3>
        <p x-show="selected.length === 0" class="mt-2 text-sm text-text-muted">Ningún dispositivo seleccionado.</p>
        <ul class="mt-2 space-y-2">
            <template x-for="key in selected" :key="key">
                <li class="flex items-center gap-2 text-sm"><span class="min-w-0 flex-1 break-words" x-text="label(key)"></span><button type="button" @click="remove(key)" :aria-label="'Quitar ' + label(key)" class="shrink-0 rounded px-2 py-1 text-danger hover:bg-danger/10">Quitar</button></li>
            </template>
        </ul>
    </section>
    <div class="max-h-80 space-y-2 overflow-y-auto">
        <template x-for="device in visible" :key="device.key">
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-white p-3">
                <input type="checkbox" :checked="has(device.key)" @change="toggle(device.key)" class="h-4 w-4 shrink-0 rounded border-border text-primary">
                <span class="min-w-0"><span class="block break-words text-sm font-medium text-text" x-text="label(device.key)"></span><span class="block break-words text-xs text-text-muted" x-text="[device.establishment, device.group_name].filter(Boolean).join(' · ')"></span></span>
            </label>
        </template>
        <p x-show="visible.length === 0" class="p-4 text-sm text-text-muted">No hay equipos para estos filtros.</p>
    </div>
</div>
