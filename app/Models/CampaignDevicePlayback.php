<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDevicePlayback extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'powered_off' => 'boolean', 'baseline' => 'array', 'baseline_closed_at' => 'datetime', 'wl35_wait_started_at' => 'datetime'];
    }

    public function preserves(CampaignDeviceFile $file): bool
    {
        $session = $this->baseline['session_id'] ?? null;
        $copies = $session ? CampaignBaselineFile::where('playback_id', $this->id)->where('session_id', $session)->get() : collect();
        if ($copies->isNotEmpty()) {
            // A partial T15 backup has not removed any originals. Preserve the complete
            // original prefix, not just the entries copied before the policy changed.
            if (($this->baseline['type'] ?? null) === 'wl35'
                && $copies->every(fn ($copy) => $copy->state === 'backed_up')
                && $file->video_index !== null && $file->video_index <= ($this->baseline['count'] ?? 0)) {
                return true;
            }

            return $copies->whereIn('state', ['backed_up', 'restored', 'removing', 'restoring', 'needs_review'])->contains(function ($copy) use ($file): bool {
                if (($this->baseline['type'] ?? null) === 'z2') {
                    return $file->filename === ($copy->operation['current_filename'] ?? $copy->filename);
                }
                $identity = $file->evidence['upload_identity'] ?? null;

                return $identity !== null && $identity === ($copy->operation['current_identity'] ?? $copy->upload_identity);
            });
        }
        if (($this->baseline['type'] ?? null) === 'z2') {
            return in_array($file->filename, $this->baseline['files'] ?? [], true);
        }

        return ($this->baseline['type'] ?? null) === 'wl35' && $file->video_index !== null
            && $file->video_index <= ($this->baseline['count'] ?? 0);
    }
}
