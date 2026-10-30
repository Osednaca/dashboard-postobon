<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignBaselineFile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['position' => 'integer', 'media_id' => 'integer', 'original_index' => 'integer', 'backup_size' => 'integer', 'operation' => 'array'];
    }
}
