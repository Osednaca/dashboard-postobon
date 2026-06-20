<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeDeviceLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_id.required' => 'El dispositivo es obligatorio.',
            'device_id.integer' => 'El ID del dispositivo debe ser un número entero.',
            'device_id.exists' => 'El dispositivo seleccionado no existe.',
            'location_id.required' => 'La ubicación es obligatoria.',
            'location_id.integer' => 'El ID de la ubicación debe ser un número entero.',
            'location_id.exists' => 'La ubicación seleccionada no existe.',
        ];
    }
}
