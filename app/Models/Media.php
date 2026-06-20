<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'name',
        'original_name',
        'file_path',
        'mime_type',
        'size',
        'duration',
        'thumbnail',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_media')
            ->withPivot('order')
            ->withTimestamps();
    }

    public function campaignMedia(): HasMany
    {
        return $this->hasMany(CampaignMedia::class);
    }
}
