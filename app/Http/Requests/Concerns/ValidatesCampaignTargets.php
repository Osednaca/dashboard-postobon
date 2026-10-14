<?php

namespace App\Http\Requests\Concerns;

use App\Services\CampaignAudienceResolver;
use App\Services\CampaignTargetCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

trait ValidatesCampaignTargets
{
    protected function prepareCampaignTargets(): void
    {
        if ($this->input('audience_mode') === 'all') {
            $this->merge([
                'target_devices' => [], 'segment_cities' => [], 'segment_groups' => [],
                'audience_business_type_ids' => [], 'audience_establishment_id' => null,
            ]);

            return;
        }
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
            'audience_mode' => ['sometimes', 'required', Rule::in(['manual', 'segment', 'establishment', 'all'])],
            'audience_business_type_ids' => ['nullable', 'array', 'max:1000'],
            'audience_business_type_ids.*' => ['required', 'integer', 'distinct', 'exists:business_types,id'],
            'audience_establishment_id' => ['required_if:audience_mode,establishment', 'nullable', 'integer', 'exists:establishments,id'],
            'target_selection_present' => ['sometimes', 'boolean'],
            'target_devices' => ['sometimes', 'array', 'max:10000'],
            'target_devices.*' => ['required', 'string', 'distinct', Rule::in($keys)],
            'segment_cities.*' => ['required', 'string', 'max:255', 'distinct'],
            'segment_groups.*' => ['required', 'integer', 'distinct', Rule::exists('groups', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function validateCampaignAudience(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        if (! $this->exists('audience_mode')) {
            $campaign = $this->route('campaign');
            $changesStructuredAudience = in_array($campaign?->audience_mode, ['segment', 'establishment', 'all'], true)
                && ($this->exists('segment_groups') || $this->exists('segment_cities') || $this->exists('target_devices'));
            if ($this->exists('audience_business_type_ids') || $this->exists('audience_establishment_id') || $changesStructuredAudience) {
                $validator->errors()->add('audience_mode', 'Indica cómo deseas seleccionar los destinatarios.');
            }

            return;
        }
        try {
            app(CampaignAudienceResolver::class)->resolve($validator->validated());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }
    }

    protected function campaignTargetMessages(): array
    {
        return [
            'audience_mode.in' => 'La forma de seleccionar destinatarios no es válida.',
            'audience_establishment_id.required_if' => 'Selecciona un establecimiento.',
            'audience_establishment_id.exists' => 'El establecimiento seleccionado ya no está disponible.',
            'audience_business_type_ids.array' => 'Los tipos de negocio deben ser una lista.',
            'audience_business_type_ids.*.exists' => 'Uno de los tipos de negocio seleccionados ya no está disponible.',
            'segment_groups.*.exists' => 'Uno de los grupos seleccionados ya no está disponible.',
            'target_devices.array' => 'La selección de dispositivos debe ser una lista.',
            'target_devices.*.in' => 'Uno de los dispositivos seleccionados ya no está disponible. Quítalo de la selección.',
            'target_devices.*.distinct' => 'Un dispositivo no puede repetirse en la selección.',
        ];
    }
}
