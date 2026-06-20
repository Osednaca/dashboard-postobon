<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeviceOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['required', 'integer', 'exists:devices,id'],
            'action' => ['required', 'in:power_on,power_off,disable,enable'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_ids.required' => 'Debe seleccionar al menos un dispositivo.',
            'device_ids.array' => 'Los dispositivos deben ser un arreglo.',
            'device_ids.min' => 'Debe seleccionar al menos un dispositivo.',
            'device_ids.*.required' => 'Cada dispositivo debe tener un ID.',
            'device_ids.*.integer' => 'El ID del dispositivo debe ser un número entero.',
            'device_ids.*.exists' => 'Uno de los dispositivos seleccionados no existe.',
            'action.required' => 'La acción es obligatoria.',
            'action.in' => 'La acción seleccionada no es válida.',
        ];
    }
}
