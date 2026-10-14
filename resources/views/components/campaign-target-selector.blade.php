@props(['campaign' => null])
@php
    $catalogService = app(\App\Services\CampaignTargetCatalog::class);
    $catalog = $catalogService->all()->values()->all();
    $retryAudience = session()->hasOldInput('audience_mode');
    $initial = old('target_devices', session()->hasOldInput('target_selection_present') ? [] : ($campaign ? $catalogService->forCampaign($campaign)->pluck('key')->all() : []));
    $criteria = [
        'mode' => old('audience_mode', $campaign?->audience_mode ?? 'manual'),
        'businessTypes' => old('audience_business_type_ids', $retryAudience ? [] : ($campaign?->audience_business_type_ids ?? [])),
        'groups' => old('segment_groups', $retryAudience ? [] : ($campaign?->segment_groups ?? [])),
        'establishment' => old('audience_establishment_id', $retryAudience ? null : $campaign?->audience_establishment_id),
    ];
    $options = [
        'businessTypes' => \App\Models\BusinessType::orderBy('name')->get(['id', 'name'])->toArray(),
        'groups' => \App\Models\Group::orderBy('name')->get(['id', 'name'])->toArray(),
        'establishments' => \App\Models\Establishment::orderBy('name')->get(['id', 'name', 'address'])->toArray(),
    ];
@endphp
<div x-data="campaignTargetSelector(@js($catalog), @js($initial), @js($criteria), @js($options))" class="space-y-4" data-campaign-target-selector>
    <label class="block text-sm font-medium text-text">Seleccionar destinatarios por
        <select name="audience_mode" x-model="mode" class="mt-1 w-full rounded-lg border border-border bg-white p-2">
            <option value="manual">Dispositivos individuales</option>
            <option value="segment">Tipo de negocio y grupo</option>
            <option value="establishment">Un establecimiento</option>
        </select>
    </label>
    <div x-show="mode === 'segment'" x-cloak class="space-y-3">
        <p class="text-sm text-text-muted">Combina tipos de negocio con grupos. Por ejemplo: Oxxo y Medellín incluye únicamente los equipos de establecimientos Oxxo que pertenecen al grupo Medellín.</p>
        <p class="text-xs text-text-muted">Entre tipos o entre grupos se admite cualquiera de los marcados; entre ambas categorías deben cumplirse las dos. El tipo de negocio se toma del establecimiento vinculado al equipo.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <fieldset class="rounded-lg border border-border p-3">
                <legend class="px-1 text-sm font-medium">Tipos de negocio</legend>
                <div class="max-h-48 space-y-2 overflow-y-auto">
                    <template x-for="item in businessTypeOptions" :key="item.id">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="audience_business_type_ids[]" :value="String(item.id)" x-model="businessTypes" :disabled="mode !== 'segment'" class="rounded border-border text-primary"><span x-text="item.name"></span></label>
                    </template>
                    <p x-show="businessTypeOptions.length === 0" class="text-sm text-text-muted">No hay tipos de negocio registrados.</p>
                </div>
            </fieldset>
            <fieldset class="rounded-lg border border-border p-3">
                <legend class="px-1 text-sm font-medium">Grupos</legend>
                <div class="max-h-48 space-y-2 overflow-y-auto">
                    <template x-for="item in segmentGroupOptions" :key="item.id">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="segment_groups[]" :value="String(item.id)" x-model="segmentGroups" :disabled="mode !== 'segment'" class="rounded border-border text-primary"><span x-text="item.name"></span></label>
                    </template>
                    <p x-show="segmentGroupOptions.length === 0" class="text-sm text-text-muted">No hay grupos registrados.</p>
                </div>
            </fieldset>
        </div>
    </div>
    <label x-show="mode === 'establishment'" x-cloak class="block text-sm font-medium text-text">Establecimiento
        <select name="audience_establishment_id" x-model="establishment" :disabled="mode !== 'establishment'" class="mt-1 w-full rounded-lg border border-border bg-white p-2">
            <option value="">Selecciona un establecimiento</option>
            <template x-for="item in establishmentOptions" :key="item.id"><option :value="String(item.id)" x-text="[item.name, item.address].filter(Boolean).join(' · ')"></option></template>
        </select>
        <span class="mt-1 block text-xs font-normal text-text-muted">Incluye solo los dispositivos vinculados a este establecimiento, sin importar su grupo.</span>
    </label>
    @foreach(['audience_mode', 'audience_business_type_ids', 'audience_business_type_ids.*', 'audience_establishment_id', 'segment_groups', 'segment_groups.*'] as $field)
        @foreach($errors->get($field) as $messages)
            @foreach((array) $messages as $message)<p class="text-sm text-danger" role="alert">{{ $message }}</p>@endforeach
        @endforeach
    @endforeach
    <p x-show="mode === 'manual'" class="text-sm text-text-muted">Selecciona equipos individuales de una o varias ciudades. Los filtros sólo cambian los equipos visibles; conservan tu selección y no agregan otros destinatarios.</p>
    <input type="hidden" name="target_selection_present" value="1">
    <template x-for="key in selected" :key="key"><input type="hidden" name="target_devices[]" :value="key" :disabled="mode !== 'manual'"></template>
    @error('target_devices')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    @foreach($errors->get('target_devices.*') as $messages)
        @foreach($messages as $message)<p class="text-sm text-danger" role="alert">{{ $message }}</p>@endforeach
    @endforeach
    <div x-show="mode === 'manual'" class="grid gap-3 sm:grid-cols-2">
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
        <h3 class="text-sm font-semibold text-text" aria-live="polite">Destinatarios (<span x-text="recipients.length">0</span>)</h3>
        <p x-show="recipients.length === 0" class="mt-2 text-sm text-text-muted" x-text="mode === 'manual' ? 'Ningún dispositivo seleccionado.' : 'No hay dispositivos que coincidan. Selecciona o ajusta los criterios.'"></p>
        <p x-show="mode !== 'manual'" class="mt-2 text-xs text-text-muted">Al guardar se fija esta audiencia con los equipos que coincidan en ese momento. Los cambios posteriores en grupos o establecimientos no agregan destinatarios automáticamente; vuelve a editar y guardar para recalcular.</p>
        <ul class="mt-2 max-h-64 space-y-2 overflow-y-auto">
            <template x-for="key in recipients" :key="key">
                <li class="flex items-center gap-2 text-sm"><span class="min-w-0 flex-1 break-words" x-text="label(key)"></span><button x-show="mode === 'manual'" type="button" @click="remove(key)" :aria-label="'Quitar ' + label(key)" class="shrink-0 rounded px-2 py-1 text-danger hover:bg-danger/10">Quitar</button></li>
            </template>
        </ul>
    </section>
    <div x-show="mode === 'manual'" class="max-h-80 space-y-2 overflow-y-auto">
        <template x-for="device in visible" :key="device.key">
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-white p-3">
                <input type="checkbox" :checked="has(device.key)" @change="toggle(device.key)" class="h-4 w-4 shrink-0 rounded border-border text-primary">
                <span class="min-w-0"><span class="block break-words text-sm font-medium text-text" x-text="label(device.key)"></span><span class="block break-words text-xs text-text-muted" x-text="[device.establishment, device.group_name].filter(Boolean).join(' · ')"></span></span>
            </label>
        </template>
        <p x-show="visible.length === 0" class="p-4 text-sm text-text-muted">No hay equipos para estos filtros.</p>
    </div>
</div>
