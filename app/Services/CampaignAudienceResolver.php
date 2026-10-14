<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampaignAudienceResolver
{
    public function __construct(private CampaignTargetCatalog $catalog) {}

    /** Resolve criteria locally; publishing must consume the saved snapshot, never these filters. */
    public function resolve(array $data): array
    {
        $mode = $data['audience_mode'] ?? null;
        if ($mode === null) {
            return $data;
        }

        Validator::make($data, [
            'audience_mode' => ['required', Rule::in(['manual', 'segment', 'establishment'])],
            'audience_business_type_ids' => ['nullable', 'array', 'max:1000'],
            'audience_business_type_ids.*' => ['required', 'integer', 'distinct', 'exists:business_types,id'],
            'segment_groups' => ['nullable', 'array', 'max:1000'],
            'segment_groups.*' => ['required', 'integer', 'distinct', Rule::exists('groups', 'id')->whereNull('deleted_at')],
            'audience_establishment_id' => [Rule::requiredIf($mode === 'establishment'), 'nullable', 'integer', 'exists:establishments,id'],
        ])->validate();

        if ($mode === 'manual') {
            $data['audience_business_type_ids'] = [];
            $data['audience_establishment_id'] = null;

            return $data;
        }

        $types = array_map('intval', $data['audience_business_type_ids'] ?? []);
        $groups = array_map('intval', $data['segment_groups'] ?? []);
        if ($mode === 'segment' && $types === [] && $groups === []) {
            throw ValidationException::withMessages(['audience_mode' => 'Selecciona al menos un tipo de negocio o un grupo para segmentar.']);
        }

        $establishment = (int) ($data['audience_establishment_id'] ?? 0);
        $keys = $this->catalog->all()->filter(function (array $device) use ($mode, $types, $groups, $establishment): bool {
            if ($mode === 'establishment') {
                return $device['establishment_id'] === $establishment;
            }

            return ($types === [] || in_array($device['business_type_id'], $types, true))
                && ($groups === [] || in_array($device['group_id'], $groups, true));
        })->keys()->all();

        if ($keys === []) {
            throw ValidationException::withMessages(['audience_mode' => 'No hay dispositivos que coincidan con esta audiencia. Ajusta los criterios o vincula equipos al establecimiento.']);
        }

        $data['target_devices'] = $keys;
        $data['segment_cities'] = [];
        $data['segment_groups'] = $mode === 'segment' ? $groups : [];
        $data['audience_business_type_ids'] = $mode === 'segment' ? $types : [];
        $data['audience_establishment_id'] = $mode === 'establishment' ? $establishment : null;

        return $data;
    }
}
