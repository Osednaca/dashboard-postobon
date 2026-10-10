@props(['media', 'deviceKey' => null])
<div x-data="libraryPlayback(@js(route('fleet.upload.status', ['fleetUpload' => '__UPLOAD_ID__'])))">
    <form action="{{ route('instant-play.media') }}" method="POST" @submit.prevent="submit($event)" class="space-y-4">
        @csrf
        @if($deviceKey)
            <input type="hidden" name="targets[]" value="{{ $deviceKey }}">
        @else
            <template x-for="key in selectedFleetKeys" :key="'library-'+key"><input type="hidden" name="targets[]" :value="key"></template>
        @endif
        <label class="block">
            <span class="mb-1.5 block text-sm font-medium text-text">Video de la biblioteca</span>
            <select name="media_id" required :disabled="busy" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary">
                <option value="">Seleccionar video</option>
                @foreach($media as $item)
                    <option value="{{ $item->id }}" @selected((string) old('media_id') === (string) $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </label>
        <p class="text-xs leading-5 text-text-light">Usa el mismo archivo de reproducción instantánea. La operación continúa en segundo plano.</p>
        <div x-show="status !== 'idle'" role="status" class="rounded-lg bg-surface p-3 text-sm text-text-light">
            <p x-text="message"></p>
            <progress x-show="busy && status !== 'session_expired'" :value="progress" max="100" class="mt-2 w-full" aria-label="Progreso de reproducción"></progress>
            <a href="{{ route('instant-play.index') }}" :href="@js(route('instant-play.index')) + (id ? '?upload_id=' + encodeURIComponent(id) : '')" class="mt-2 inline-block text-xs font-semibold text-primary hover:underline">Consultar operación en reproducción instantánea</a>
        </div>
        <div class="flex justify-end">
            <button type="submit" :disabled="busy" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" x-text="status === 'session_expired' ? 'Sesión caducada' : (busy ? 'Enviando…' : 'Reproducir video')">Reproducir video</button>
        </div>
    </form>
</div>
