<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la ubicación es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'address.required' => 'La dirección es obligatoria.',
            'address.max' => 'La dirección no puede tener más de 500 caracteres.',
            'city.required' => 'La ciudad es obligatoria.',
            'country.required' => 'El país es obligatorio.',
            'latitude.required' => 'La latitud es obligatoria.',
            'latitude.numeric' => 'La latitud debe ser un número.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.required' => 'La longitud es obligatoria.',
            'longitude.numeric' => 'La longitud debe ser un número.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'contact_name.max' => 'El nombre de contacto no puede tener más de 255 caracteres.',
            'phone.max' => 'El teléfono no puede tener más de 50 caracteres.',
        ];
    }
}
