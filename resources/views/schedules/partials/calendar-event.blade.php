<a href="{{ route('schedules.show', $event) }}" class="mt-2 block rounded-md bg-surface p-2 text-xs hover:ring-1 hover:ring-primary focus-visible:ring-2 focus-visible:ring-primary">
    <span class="block font-medium text-text break-words">{{ $event->scheduled_at->timezone(config('app.timezone'))->format('H:i') }} · {{ $event->name }}</span>
    <span class="my-1 block text-text-muted break-words">{{ $event->targetLabel() }}</span>
    <x-status-badge :status="$event->status" size="sm" />
</a>
