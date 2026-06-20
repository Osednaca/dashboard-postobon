<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deviceId = $this->route('device')?->id ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'mac_address' => ['sometimes', 'string', 'max:255', Rule::unique('devices')->ignore($deviceId)],
            'firmware' => ['nullable', 'string', 'max:255'],
            'hardware' => ['nullable', 'string', 'max:255'],
            'rpm' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive,maintenance'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'working_hours' => ['nullable', 'numeric', 'min:0'],
            'power_status' => ['sometimes', 'in:on,off'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'mac_address.unique' => 'La dirección MAC ya está registrada.',
            'firmware.max' => 'El firmware no puede tener más de 255 caracteres.',
            'hardware.max' => 'El hardware no puede tener más de 255 caracteres.',
            'rpm.integer' => 'Las RPM deben ser un número entero.',
            'rpm.min' => 'Las RPM no pueden ser negativas.',
            'status.in' => 'El estado seleccionado no es válido.',
            'location_id.exists' => 'La ubicación seleccionada no existe.',
            'group_id.exists' => 'El grupo seleccionado no existe.',
            'working_hours.numeric' => 'Las horas de trabajo deben ser un número.',
            'working_hours.min' => 'Las horas de trabajo no pueden ser negativas.',
            'power_status.in' => 'El estado de energía seleccionado no es válido.',
        ];
    }
}
