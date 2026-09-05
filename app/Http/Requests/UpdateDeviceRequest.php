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
            'establishment_id' => ['required', 'exists:establishments,id'],
            'mac_address' => ['sometimes', 'string', 'max:255', Rule::unique('devices')->ignore($deviceId)],
            'firmware' => ['nullable', 'string', 'max:255'],
            'hardware' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'establishment_id.required' => 'Selecciona el establecimiento del dispositivo.',
            'establishment_id.exists' => 'El establecimiento seleccionado no existe.',
            'mac_address.unique' => 'La dirección MAC ya está registrada.',
            'firmware.max' => 'El firmware no puede tener más de 255 caracteres.',
            'hardware.max' => 'El hardware no puede tener más de 255 caracteres.',
        ];
    }
}
