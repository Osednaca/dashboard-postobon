<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_type_id' => ['required', 'exists:business_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'wifi_ssid' => ['nullable', 'string', 'max:255'],
            'wifi_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_type_id.required' => 'Selecciona el tipo de negocio.',
            'business_type_id.exists' => 'El tipo de negocio seleccionado no existe.',
            'name.required' => 'El nombre del establecimiento es obligatorio.',
            'address.required' => 'La dirección del establecimiento es obligatoria.',
            'latitude.required_with' => 'Indica la latitud junto con la longitud.',
            'longitude.required_with' => 'Indica la longitud junto con la latitud.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
        ];
    }
}
