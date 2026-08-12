<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'action' => ['required', 'in:power_on,power_off,disable,enable,change_group,change_location,unbind'],
            'target_group_id' => [Rule::requiredIf($this->input('action') === 'change_group'), 'nullable', 'integer', 'exists:groups,id'],
            'target_location_id' => [Rule::requiredIf($this->input('action') === 'change_location'), 'nullable', 'integer', 'exists:locations,id'],
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
            'target_group_id.exists' => 'El grupo seleccionado no existe.',
            'target_location_id.exists' => 'La ubicación seleccionada no existe.',
            'target_group_id.required' => 'Selecciona un grupo destino.',
            'target_location_id.required' => 'Selecciona una ubicación destino.',
        ];
    }
}
