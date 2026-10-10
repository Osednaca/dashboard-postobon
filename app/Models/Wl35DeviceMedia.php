<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wl35DeviceMedia extends Model
{
    protected $table = 'wl35_device_media';

    protected $fillable = [
        'device_id',
        'media_id',
        'video_index',
        'filename',
    ];

    protected function casts(): array
    {
        return [
            'media_id' => 'integer',
            'video_index' => 'integer',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function getDisplayFilenameAttribute(): string
    {
        return $this->filename ?: ($this->media?->original_name ?: ($this->media?->name ?: 'Nombre de archivo no identificado'));
    }
}
