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

    /**
     * URL de visualización/descarga del medio.
     *
     * - file_path absoluto (http) -> tal cual
     * - filename simple (nube privada) -> servido por la nube privada
     * - ruta local (media/...) -> storage público
     */
    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->file_path, 'http')) {
            return $this->file_path;
        }

        if (! str_contains($this->file_path, '/')) {
            $base = rtrim((string) config('privatecloud.base_url', ''), '/');
            if ($base !== '') {
                return $base.'/fileDownload/Videos/'.rawurlencode($this->file_path);
            }
        }

        return asset('storage/'.$this->file_path);
    }
}
