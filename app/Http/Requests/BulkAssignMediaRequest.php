<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['required', 'integer', 'distinct', 'exists:devices,id'],
            'media_id' => ['required', 'integer', 'exists:media,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_ids.required' => 'Selecciona al menos un dispositivo.',
            'device_ids.min' => 'Selecciona al menos un dispositivo.',
            'device_ids.*.distinct' => 'La selección contiene un dispositivo duplicado.',
            'device_ids.*.exists' => 'Uno de los dispositivos seleccionados ya no existe.',
            'media_id.required' => 'Selecciona el medio que deseas asignar.',
            'media_id.exists' => 'El medio seleccionado ya no existe.',
        ];
    }
}
