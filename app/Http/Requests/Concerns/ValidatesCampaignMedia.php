<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesCampaignMedia
{
    protected function prepareCampaignMedia(): void
    {
        if (! $this->exists('media_ids')) {
            if ($this->exists('videos')) {
                $videos = $this->input('videos');
                $this->merge(['media_ids' => is_string($videos)
                    ? array_values(array_filter(array_map('trim', explode(',', $videos)), fn ($id) => $id !== ''))
                    : ($videos === null ? [] : $videos)]);
            } elseif ($this->boolean('media_selection_present')) {
                $this->merge(['media_ids' => []]);
            }
        }
        if (is_array($this->input('media_ids'))) {
            $this->merge(['media_ids' => array_values($this->input('media_ids'))]);
        }
    }

    protected function campaignMediaRules(): array
    {
        return [
            'media_selection_present' => ['sometimes', 'boolean'],
            'media_ids' => ['sometimes', 'array'],
            'media_ids.*' => ['required', 'integer', 'distinct', Rule::exists('media', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function campaignMediaMessages(): array
    {
        return [
            'media_ids.array' => 'La selección de medios no es válida.',
            'media_ids.*.integer' => 'Uno de los medios seleccionados no es válido.',
            'media_ids.*.distinct' => 'Un medio no puede repetirse en la campaña.',
            'media_ids.*.exists' => 'Uno de los medios seleccionados ya no está disponible. Quítalo de la selección.',
        ];
    }
}
