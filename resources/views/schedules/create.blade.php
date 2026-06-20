<x-app-layout>
    <x-slot name="title">Nueva Programación</x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('schedules.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Nueva Programación</h2>
                <p class="mt-1 text-sm text-text-muted">Programa una tarea para los dispositivos</p>
            </div>
        </div>

        <form action="{{ route('schedules.store') }}" method="POST" class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]" x-data="{ type: 'power_on' }">
            @csrf

            <div class="p-6 space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-text mb-1.5">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors" placeholder="Ej. Apagado nocturno">
                    @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-text mb-1.5">Tipo <span class="text-danger">*</span></label>
                    <select name="type" id="type" x-model="type" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                        <option value="power_on">Encendido</option>
                        <option value="power_off">Apagado</option>
                        <option value="change_content">Cambio Contenido</option>
                        <option value="activate_campaign">Activar Campaña</option>
                    </select>
                    @error('type')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="device_id" class="block text-sm font-medium text-text mb-1.5">Dispositivo</label>
                        <select name="device_id" id="device_id" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                            <option value="">Todos</option>
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}">{{ $device->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="group_id" class="block text-sm font-medium text-text mb-1.5">Grupo</label>
                        <select name="group_id" id="group_id" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                            <option value="">Todos</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Conditional fields based on type --}}
                <div x-show="type === 'activate_campaign'" x-transition>
                    <label for="campaign_id" class="block text-sm font-medium text-text mb-1.5">Campaña <span class="text-danger">*</span></label>
                    <select name="campaign_id" id="campaign_id" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                        <option value="">Seleccionar campaña</option>
                        @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                    @error('campaign_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div x-show="type === 'change_content'" x-transition>
                    <label for="content_id" class="block text-sm font-medium text-text mb-1.5">Contenido <span class="text-danger">*</span></label>
                    <select name="content_id" id="content_id" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                        <option value="">Seleccionar contenido</option>
                        @foreach($media as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                    @error('content_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="scheduled_at" class="block text-sm font-medium text-text mb-1.5">Fecha programada <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="scheduled_at" id="scheduled_at" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                    @error('scheduled_at')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="px-6 py-4 bg-surface border-t border-border flex items-center justify-end gap-3">
                <a href="{{ route('schedules.index') }}" class="px-4 py-2.5 rounded-lg text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">Crear Programación</button>
            </div>
        </form>
    </div>
</x-app-layout>
