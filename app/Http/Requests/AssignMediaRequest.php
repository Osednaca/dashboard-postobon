<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer', 'exists:media,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'campaign_id.required' => 'La campaña es obligatoria.',
            'campaign_id.integer' => 'El ID de la campaña debe ser un número entero.',
            'campaign_id.exists' => 'La campaña seleccionada no existe.',
            'media_ids.required' => 'Debe seleccionar al menos un medio.',
            'media_ids.array' => 'Los medios deben ser un arreglo.',
            'media_ids.min' => 'Debe seleccionar al menos un medio.',
            'media_ids.*.required' => 'Cada medio debe tener un ID.',
            'media_ids.*.integer' => 'El ID del medio debe ser un número entero.',
            'media_ids.*.exists' => 'Uno de los medios seleccionados no existe.',
        ];
    }
}
