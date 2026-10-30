<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDevicePlayback extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'powered_off' => 'boolean', 'baseline' => 'array', 'baseline_closed_at' => 'datetime'];
    }

    public function preserves(CampaignDeviceFile $file): bool
    {
        if (($this->baseline['type'] ?? null) === 'z2') {
            return in_array($file->filename, $this->baseline['files'] ?? [], true);
        }

        return ($this->baseline['type'] ?? null) === 'wl35' && $file->video_index !== null
            && $file->video_index <= ($this->baseline['count'] ?? 0);
    }
}
