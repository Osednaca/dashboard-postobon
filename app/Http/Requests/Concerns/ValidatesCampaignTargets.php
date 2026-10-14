<?php

namespace App\Http\Requests\Concerns;

use App\Services\CampaignTargetCatalog;
use Illuminate\Validation\Rule;

trait ValidatesCampaignTargets
{
    protected function prepareCampaignTargets(): void
    {
        foreach (['cities' => 'segment_cities', 'groups' => 'segment_groups'] as $alias => $field) {
            if (! $this->exists($field) && $this->exists($alias)) {
                $this->merge([$field => $this->input($alias) ?? []]);
            }
        }
        if (! $this->exists('target_devices') && $this->boolean('target_selection_present')) {
            $this->merge(['target_devices' => []]);
        }
        if (is_array($this->input('target_devices'))) {
            $keys = array_map(function ($key) {
                if (is_string($key) && str_starts_with($key, 'z2:')) {
                    return CampaignTargetCatalog::z2Key(substr($key, 3));
                }

                return $key;
            }, array_values($this->input('target_devices')));
            $this->merge(['target_devices' => $keys]);
        }
    }

    protected function campaignTargetRules(): array
    {
        $keys = app(CampaignTargetCatalog::class)->all()->keys()->all();

        return [
            'target_selection_present' => ['sometimes', 'boolean'],
            'target_devices' => ['sometimes', 'array', 'max:10000'],
            'target_devices.*' => ['required', 'string', 'distinct', Rule::in($keys)],
            'segment_cities.*' => ['required', 'string', 'max:255', 'distinct'],
            'segment_groups.*' => ['required', 'integer', 'distinct', Rule::exists('groups', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function campaignTargetMessages(): array
    {
        return [
            'target_devices.array' => 'La selección de dispositivos debe ser una lista.',
            'target_devices.*.in' => 'Uno de los dispositivos seleccionados ya no está disponible. Quítalo de la selección.',
            'target_devices.*.distinct' => 'Un dispositivo no puede repetirse en la selección.',
        ];
    }
}
