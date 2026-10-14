<div aria-live="polite">
    <p x-show="stateFailed" x-cloak class="rounded-lg bg-warning/10 px-3 py-2 text-sm text-amber-900">No se pudo consultar el estado. Reintentaremos automáticamente.</p>
    <p x-show="commandMessage" x-cloak class="rounded-lg px-3 py-2 text-sm" :class="commandFailed ? 'bg-danger/10 text-danger' : 'bg-primary/5 text-primary'" x-text="commandMessage"></p>
</div>
