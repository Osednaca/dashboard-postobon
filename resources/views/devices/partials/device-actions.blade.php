@php
    $isMenu = $variant === 'menu';
    $buttonClass = $isMenu ? 'device-menu-action' : 'device-quick-action';
    $powerLabel = $row['powered'] ? 'Apagar dispositivo' : 'Encender dispositivo';
    $bluetoothLabel = $row['bluetooth'] ? 'Apagar Bluetooth' : 'Encender Bluetooth';
@endphp
<button type="button" class="{{ $buttonClass }}" @click="openPlayback([{{ Js::from($row['key']) }}], {{ Js::from($row['name']) }})" aria-label="Reproducir contenido en {{ $row['name'] }}" title="Reproducir contenido"><x-device-action-icon name="play" /><span class="{{ $isMenu ? '' : 'sr-only' }}">Reproducir contenido</span></button>
@foreach([true, false] as $turnOn)
    @php($label = $turnOn ? 'Encender dispositivo' : 'Apagar dispositivo')
    <form action="{{ $turnOn ? $row['powerOnUrl'] : $row['powerOffUrl'] }}" method="POST"
        @submit.prevent="sendDeviceCommand($event.currentTarget, $event.submitter)"
        @unless($isMenu) x-show="rows[{{ $index }}].powered {{ $turnOn ? '!==' : '===' }} true" @endunless>
        @csrf
        @if($row['type'] === 'wl35')<input type="hidden" name="command" value="power"><input type="hidden" name="targets[]" value="{{ $row['key'] }}"><input type="hidden" name="value" value="{{ $turnOn ? 1 : 0 }}">@endif
        <button class="{{ $buttonClass }} disabled:opacity-40" :disabled="!canControl(rows[{{ $index }}])" aria-label="{{ $label }}: {{ $row['name'] }}" title="{{ $label }}"><x-device-action-icon name="power" /><span class="{{ $isMenu ? '' : 'sr-only' }}">{{ $label }}</span></button>
    </form>
@endforeach
<form :action="rows[{{ $index }}].bluetooth ? {{ Js::from($row['bluetoothOffUrl']) }} : {{ Js::from($row['bluetoothOnUrl']) }}" method="POST" @submit.prevent="sendDeviceCommand($event.currentTarget, $event.submitter)">
    @csrf
    @if($row['type'] === 'wl35')<input type="hidden" name="command" value="bluetooth"><input type="hidden" name="targets[]" value="{{ $row['key'] }}"><input type="hidden" name="value" :value="rows[{{ $index }}].bluetooth ? 0 : 1">@endif
    <button class="{{ $buttonClass }} disabled:opacity-40" :class="{ 'device-bluetooth-active': rows[{{ $index }}].bluetooth === true }" :disabled="!canControl(rows[{{ $index }}], 'bluetooth')" :aria-label="(rows[{{ $index }}].bluetooth ? 'Apagar' : 'Encender') + ' Bluetooth: ' + {{ Js::from($row['name']) }}" :title="rows[{{ $index }}].bluetooth ? 'Apagar Bluetooth' : 'Encender Bluetooth'"><x-device-action-icon name="bluetooth" /><span class="{{ $isMenu ? '' : 'sr-only' }}" x-text="rows[{{ $index }}].bluetooth ? 'Apagar Bluetooth' : 'Encender Bluetooth'"></span></button>
</form>
@if($isMenu)
    <a href="{{ $row['detailsUrl'] }}" class="device-menu-action"><x-device-action-icon name="info" />Obtener información</a>
    @if($row['editUrl'])<a href="{{ $row['editUrl'] }}" class="device-menu-action"><x-device-action-icon name="edit" />Editar dispositivo</a>@endif
    <div class="device-menu-danger">
        <button type="button" class="device-menu-action" @click="closeActions(false); openFleetFormat([{{ Js::from($row['key']) }}])"><x-device-action-icon name="card" />Formatear tarjeta SD</button>
        @if($row['localId'])<button type="button" class="device-menu-action" @click="closeActions(false); deleteId={{ Js::from($row['localId']) }}; deleteName={{ Js::from($row['name']) }}; showDeleteModal=true"><x-device-action-icon name="trash" />Eliminar dispositivo</button>@endif
    </div>
@endif
