@php
    $schedule = $schedule ?? null;
    $formType = old('type', $schedule?->type ?? 'power_on');
    $recurrence = old('recurrence_type', $schedule?->recurrence_type ?? 'once');
    $scope = old('target_scope', $schedule?->device_id ? 'device' : ($schedule?->group_id ? 'group' : 'all'));
    $selectedDays = array_map('intval', old('recurrence_days', $schedule?->recurrence_days ?? [1, 2, 3, 4, 5]));
    $scheduledAt = old('scheduled_at', $schedule?->scheduled_at?->format('Y-m-d\TH:i'));
    $startsOn = old('starts_on', $schedule?->scheduled_at?->format('Y-m-d') ?? now(config('app.timezone'))->format('Y-m-d'));
    $recurrenceTime = old('recurrence_time', $schedule?->recurrence_time ? substr($schedule->recurrence_time, 0, 5) : '23:00');
    $recurrenceDay = old('recurrence_day', $schedule?->recurrence_day ?? 1);
    $recurrenceEndsAt = old('recurrence_ends_at', $schedule?->recurrence_ends_at?->format('Y-m-d'));
@endphp

<div class="p-6 sm:p-8 space-y-8"
     x-data="{ type: '{{ $formType }}', scope: '{{ $scope }}', recurrence: '{{ $recurrence }}', selectedDays: @js($selectedDays) }">
    @if($errors->any())
        <div class="rounded-xl border border-danger/25 bg-danger/5 px-4 py-3 text-sm text-danger">
            Revisa los campos marcados antes de guardar la programación.
        </div>
    @endif

    <section class="space-y-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">01 · Instrucción</p>
            <h3 class="mt-1 text-lg font-semibold text-text">¿Qué debe ejecutar el sistema?</h3>
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-text mb-1.5">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" required value="{{ old('name', $schedule?->name) }}"
                   class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary"
                   placeholder="Ej. Apagado nocturno">
            @error('name')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="type" class="block text-sm font-medium text-text mb-1.5">Acción <span class="text-danger">*</span></label>
            <select name="type" id="type" x-model="type" required
                    class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="power_on">Encender</option>
                <option value="power_off">Apagar</option>
                <option value="change_content">Reproducir contenido</option>
                <option value="activate_campaign">Activar campaña</option>
                <option value="format_sd">Formatear tarjeta SD</option>
            </select>
            @error('type')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
            <p x-show="type === 'format_sd'" x-cloak class="mt-2 rounded-lg bg-warning/10 px-3 py-2 text-xs text-amber-800">
                Esta acción elimina todos los videos almacenados en los dispositivos seleccionados.
            </p>
        </div>

        <div x-show="type === 'activate_campaign'" x-cloak>
            <label for="campaign_id" class="block text-sm font-medium text-text mb-1.5">Campaña <span class="text-danger">*</span></label>
            <select name="campaign_id" id="campaign_id" :disabled="type !== 'activate_campaign'"
                    class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Seleccionar campaña</option>
                @foreach($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected(old('campaign_id', $schedule?->campaign_id) == $campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </select>
            @error('campaign_id')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div x-show="type === 'change_content'" x-cloak>
            <label for="content_id" class="block text-sm font-medium text-text mb-1.5">Contenido <span class="text-danger">*</span></label>
            <select name="content_id" id="content_id" :disabled="type !== 'change_content'"
                    class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Seleccionar contenido</option>
                @foreach($media as $item)
                    <option value="{{ $item->id }}" @selected(old('content_id', $schedule?->content_id) == $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
            @error('content_id')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="space-y-5 border-t border-border pt-8" x-show="type !== 'activate_campaign'" x-cloak>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">02 · Alcance</p>
            <h3 class="mt-1 text-lg font-semibold text-text">¿Dónde debe ejecutarse?</h3>
        </div>

        <div class="grid grid-cols-3 gap-2 rounded-xl bg-surface p-1.5">
            @foreach(['all' => 'Todos', 'device' => 'Dispositivo', 'group' => 'Grupo'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="target_scope" value="{{ $value }}" x-model="scope" class="sr-only">
                    <span class="block rounded-lg px-3 py-2.5 text-center text-sm font-medium transition-all"
                          :class="scope === '{{ $value }}' ? 'bg-white text-primary shadow-sm ring-1 ring-border' : 'text-text-muted hover:text-text'">
                        {{ $label }}
                    </span>
                </label>
            @endforeach
        </div>

        <div x-show="scope === 'device'" x-cloak>
            <label for="device_id" class="block text-sm font-medium text-text mb-1.5">Dispositivo <span class="text-danger">*</span></label>
            <select name="device_id" id="device_id" :disabled="scope !== 'device' || type === 'activate_campaign'"
                    class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Seleccionar dispositivo</option>
                @foreach($devices as $device)
                    <option value="{{ $device->id }}" @selected(old('device_id', $schedule?->device_id) == $device->id)>{{ $device->name }}</option>
                @endforeach
            </select>
            @error('device_id')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div x-show="scope === 'group'" x-cloak>
            <label for="group_id" class="block text-sm font-medium text-text mb-1.5">Grupo <span class="text-danger">*</span></label>
            <select name="group_id" id="group_id" :disabled="scope !== 'group' || type === 'activate_campaign'"
                    class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Seleccionar grupo</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}" @selected(old('group_id', $schedule?->group_id) == $group->id)>{{ $group->name }}</option>
                @endforeach
            </select>
            @error('group_id')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <p x-show="scope === 'all'" class="text-xs text-text-muted">
            La instrucción se enviará a todos los dispositivos registrados con una dirección MAC válida.
        </p>
    </section>

    <section class="space-y-5 border-t border-border pt-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">03 · Recurrencia</p>
            <h3 class="mt-1 text-lg font-semibold text-text">¿Cuándo debe repetirse?</h3>
            <p class="mt-1 text-sm text-text-muted">Zona horaria: {{ config('app.timezone') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach(['once' => 'Una vez', 'daily' => 'Diario', 'weekly' => 'Semanal', 'monthly' => 'Mensual'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="recurrence_type" value="{{ $value }}" x-model="recurrence" class="sr-only">
                    <span class="block rounded-xl border px-3 py-3 text-center text-sm font-medium transition-all"
                          :class="recurrence === '{{ $value }}' ? 'border-primary bg-primary/5 text-primary ring-1 ring-primary/20' : 'border-border bg-white text-text-muted hover:border-primary/40 hover:text-text'">
                        {{ $label }}
                    </span>
                </label>
            @endforeach
        </div>
        @error('recurrence_type')<p class="text-xs text-danger">{{ $message }}</p>@enderror

        <div x-show="recurrence === 'once'" x-cloak>
            <label for="scheduled_at" class="block text-sm font-medium text-text mb-1.5">Fecha y hora <span class="text-danger">*</span></label>
            <input type="datetime-local" name="scheduled_at" id="scheduled_at" value="{{ $scheduledAt }}"
                   :disabled="recurrence !== 'once'" :required="recurrence === 'once'"
                   class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
            @error('scheduled_at')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div x-show="recurrence !== 'once'" x-cloak class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="starts_on" class="block text-sm font-medium text-text mb-1.5">Inicia <span class="text-danger">*</span></label>
                    <input type="date" name="starts_on" id="starts_on" value="{{ $startsOn }}"
                           :disabled="recurrence === 'once'" :required="recurrence !== 'once'"
                           class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                    @error('starts_on')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="recurrence_time" class="block text-sm font-medium text-text mb-1.5">Hora <span class="text-danger">*</span></label>
                    <input type="time" name="recurrence_time" id="recurrence_time" value="{{ $recurrenceTime }}"
                           :disabled="recurrence === 'once'" :required="recurrence !== 'once'"
                           class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                    @error('recurrence_time')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="recurrence_ends_at" class="block text-sm font-medium text-text mb-1.5">Finaliza</label>
                    <input type="date" name="recurrence_ends_at" id="recurrence_ends_at" value="{{ $recurrenceEndsAt }}"
                           :disabled="recurrence === 'once'"
                           class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                    <p class="mt-1 text-xs text-text-muted">Déjalo vacío para repetir sin fecha límite.</p>
                    @error('recurrence_ends_at')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            <div x-show="recurrence === 'weekly'" x-cloak>
                <span class="block text-sm font-medium text-text mb-2">Días de la semana <span class="text-danger">*</span></span>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                    @foreach([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $day => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="recurrence_days[]" value="{{ $day }}" x-model.number="selectedDays"
                                   :disabled="recurrence !== 'weekly'" class="sr-only">
                            <span class="block rounded-lg border px-2 py-2.5 text-center text-xs font-semibold transition-all"
                                  :class="selectedDays.includes({{ $day }}) ? 'border-primary bg-primary text-white' : 'border-border bg-white text-text-muted hover:border-primary/40'">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('recurrence_days')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
            </div>

            <div x-show="recurrence === 'monthly'" x-cloak class="max-w-xs">
                <label for="recurrence_day" class="block text-sm font-medium text-text mb-1.5">Día de cada mes <span class="text-danger">*</span></label>
                <input type="number" min="1" max="31" name="recurrence_day" id="recurrence_day" value="{{ $recurrenceDay }}"
                       :disabled="recurrence !== 'monthly'" :required="recurrence === 'monthly'"
                       class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                <p class="mt-1 text-xs text-text-muted">Si el mes tiene menos días, se ejecutará el último día disponible.</p>
                @error('recurrence_day')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
</div>
