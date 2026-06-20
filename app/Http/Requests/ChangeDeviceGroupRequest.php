<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeDeviceGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_id.required' => 'El dispositivo es obligatorio.',
            'device_id.integer' => 'El ID del dispositivo debe ser un número entero.',
            'device_id.exists' => 'El dispositivo seleccionado no existe.',
            'group_id.required' => 'El grupo es obligatorio.',
            'group_id.integer' => 'El ID del grupo debe ser un número entero.',
            'group_id.exists' => 'El grupo seleccionado no existe.',
        ];
    }
}
