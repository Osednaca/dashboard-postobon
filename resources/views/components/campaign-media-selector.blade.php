@props(['media', 'selectedIds' => []])
@php
    $initial = old('media_ids', $selectedIds);
    $catalog = $media->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()->all();
@endphp
<div x-data="campaignMediaSelector(@js($catalog), @js($initial))" class="space-y-5" data-campaign-media-selector>
    <p class="text-sm text-text-muted">Revisa la vista previa y agrega los medios de la biblioteca. Usa Subir y Bajar para ordenar la selección.</p>
    <input type="hidden" name="media_selection_present" value="1">
    <template x-for="id in selected" :key="id">
        <input type="hidden" name="media_ids[]" :value="id">
    </template>
    @error('media_ids')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    @foreach($errors->get('media_ids.*') as $messages)
        @foreach($messages as $message)<p class="text-sm text-danger" role="alert">{{ $message }}</p>@endforeach
    @endforeach

    <section class="rounded-lg border border-border bg-surface p-4" aria-label="Medios seleccionados">
        <h3 class="mb-3 text-sm font-semibold text-text">Selección (<span x-text="selected.length">0</span>)</h3>
        <p x-show="selected.length === 0" class="text-sm text-text-muted">Todavía no has agregado medios.</p>
        <ol class="space-y-2">
            <template x-for="(id, index) in selected" :key="id">
                <li class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-white p-3">
                    <span class="text-sm font-semibold text-primary" x-text="index + 1"></span>
                    <span class="min-w-0 flex-1 text-sm text-text" :class="available(id) ? '' : 'text-danger'" x-text="name(id)"></span>
                    <button type="button" @click="move(id, -1)" :disabled="index === 0" :aria-label="'Subir ' + name(id)" class="rounded border border-border px-2 py-1 text-xs text-text disabled:opacity-40">Subir</button>
                    <button type="button" @click="move(id, 1)" :disabled="index === selected.length - 1" :aria-label="'Bajar ' + name(id)" class="rounded border border-border px-2 py-1 text-xs text-text disabled:opacity-40">Bajar</button>
                    <button type="button" @click="remove(id)" :aria-label="'Quitar ' + name(id)" class="rounded px-2 py-1 text-xs text-danger hover:bg-danger/10">Quitar</button>
                </li>
            </template>
        </ol>
    </section>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($media as $item)
            <article class="overflow-hidden rounded-lg border border-border bg-white" :class="has({{ $item->id }}) ? 'border-primary' : ''">
                <x-media-preview :media="$item" detail />
                <div class="space-y-2 p-3">
                    <h4 class="text-sm font-medium text-text">{{ $item->name }}</h4>
                    <p class="text-xs text-text-muted"><x-media-duration :media="$item" /></p>
                    <button type="button" @click="has({{ $item->id }}) ? remove({{ $item->id }}) : add({{ $item->id }})"
                        class="w-full rounded-lg border border-border px-3 py-2 text-sm font-medium text-primary hover:bg-primary/5"
                        :aria-pressed="has({{ $item->id }})" x-text="has({{ $item->id }}) ? 'Quitar de la selección' : 'Agregar a la selección'">Agregar a la selección</button>
                </div>
            </article>
        @empty
            <p class="col-span-full py-6 text-center text-sm text-text-muted">No hay medios disponibles. <a href="{{ route('media.create') }}" class="text-primary hover:underline">Sube uno primero</a>.</p>
        @endforelse
    </div>
</div>
