<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWl35DeviceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'establishment' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del WL35 es obligatorio.',
            'name.max' => 'El nombre no puede superar 255 caracteres.',
            'contact_email.email' => 'El correo del contacto no tiene un formato válido.',
            'address.max' => 'La dirección no puede superar 500 caracteres.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'location_id.exists' => 'La ubicación seleccionada no existe.',
            'group_id.exists' => 'El grupo seleccionado no existe.',
            'notes.max' => 'Las notas no pueden superar 2000 caracteres.',
        ];
    }
}
