<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceOnboarding extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['idempotency_key', 'request_hash', 'active_identifier'];

    protected function casts(): array
    {
        return [
            'play_mode' => 'integer',
            'sync_class' => 'integer',
            'baseline_heartbeat_count' => 'integer',
            'baseline_last_seen_at' => 'immutable_datetime',
            'configuration_reported_at' => 'immutable_datetime',
            'verification_baseline_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
