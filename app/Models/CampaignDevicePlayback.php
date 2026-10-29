<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDevicePlayback extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'powered_off' => 'boolean'];
    }
}
