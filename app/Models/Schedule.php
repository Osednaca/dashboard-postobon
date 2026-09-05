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
        'device_id',
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
            'recurrence_day' => 'integer',
            'recurrence_ends_at' => 'datetime',
        ];
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
