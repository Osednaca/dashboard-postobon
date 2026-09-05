<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'establishment_id' => ['required', 'exists:establishments,id'],
            'mac_address' => ['required', 'string', 'max:255', 'unique:devices,mac_address'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del dispositivo es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'establishment_id.required' => 'Selecciona el establecimiento del dispositivo.',
            'establishment_id.exists' => 'El establecimiento seleccionado no existe.',
            'mac_address.required' => 'La dirección MAC es obligatoria.',
            'mac_address.unique' => 'La dirección MAC ya está registrada.',
        ];
    }
}
