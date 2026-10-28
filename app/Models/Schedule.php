<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'target_scope',
        'device_id',
        'device_targets',
        'group_id',
        'campaign_id',
        'content_id',
        'scheduled_at',
        'executed_at',
        'status',
        'recurrence_type',
        'recurrence_time',
        'recurrence_days',
        'recurrence_day',
        'recurrence_ends_at',
        'last_run_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'executed_at' => 'datetime',
            'recurrence_days' => 'array',
            'device_targets' => 'array',
            'recurrence_day' => 'integer',
            'recurrence_ends_at' => 'datetime',
        ];
    }

    public function controlTargetScope(): ?string
    {
        if ($this->device_targets !== null && (! is_array($this->device_targets)
            || count(array_filter($this->device_targets, fn ($key) => is_string($key) && preg_match('/^(z2|wl35):[1-9][0-9]*$/D', $key))) !== count($this->device_targets))) {
            return null;
        }
        $hasDevice = $this->device_id !== null || ! empty($this->device_targets);
        $hasGroup = $this->group_id !== null;

        if ($this->device_id !== null && ! empty($this->device_targets)) {
            return null;
        }

        return match ($this->target_scope) {
            'all' => ! $hasDevice && ! $hasGroup ? 'all' : null,
            'device' => $hasDevice && ! $hasGroup ? 'device' : null,
            'group' => $hasGroup && ! $hasDevice ? 'group' : null,
            null => $hasDevice !== $hasGroup ? ($hasDevice ? 'device' : 'group') : null,
            default => null,
        };
    }

    /** @return list<int> */
    public function selectedDeviceIds(string $provider): array
    {
        return collect($this->device_targets ?? [])
            ->filter(fn ($key) => is_string($key) && str_starts_with($key, $provider.':'))
            ->map(fn (string $key): int => (int) substr($key, strlen($provider) + 1))
            ->all();
    }

    public function targetLabel(): string
    {
        $scope = $this->controlTargetScope();
        if ($scope === null && $this->type === 'change_content' && $this->target_scope === null
            && $this->device_id === null && $this->group_id === null && empty($this->device_targets)) {
            return 'Todos';
        }
        if ($scope === null && in_array($this->target_scope, ['device', 'group'], true)
            && $this->device_id === null && $this->group_id === null && empty($this->device_targets)) {
            return $this->target_scope === 'group' ? 'Grupo no disponible' : 'Dispositivo no disponible';
        }

        return match ($scope) {
            'all' => 'Todos',
            'device' => $this->device_targets !== null
                ? count($this->device_targets).' dispositivo(s)'
                : ($this->device?->name ?? 'Dispositivo no disponible'),
            'group' => $this->group?->name ?? 'Grupo no disponible',
            default => 'Destino por confirmar',
        };
    }

    public function isRecurring(): bool
    {
        return in_array($this->recurrence_type, ['daily', 'weekly', 'monthly'], true);
    }

    public function recurrenceLabel(): string
    {
        $time = substr((string) $this->recurrence_time, 0, 5);

        return match ($this->recurrence_type) {
            'daily' => "Todos los días a las {$time}",
            'weekly' => $this->weeklyRecurrenceLabel($time),
            'monthly' => "El día {$this->recurrence_day} de cada mes a las {$time}",
            default => 'Una sola vez',
        };
    }

    private function weeklyRecurrenceLabel(string $time): string
    {
        $names = [
            1 => 'lun',
            2 => 'mar',
            3 => 'mié',
            4 => 'jue',
            5 => 'vie',
            6 => 'sáb',
            7 => 'dom',
        ];

        $days = collect($this->recurrence_days ?? [])
            ->map(fn (int|string $day): string => $names[(int) $day] ?? '')
            ->filter()
            ->implode(', ');

        return "Cada {$days} a las {$time}";
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'content_id');
    }
}
