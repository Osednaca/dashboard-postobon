<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:500'],
            'city' => ['sometimes', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'address.max' => 'La dirección no puede tener más de 500 caracteres.',
            'city.max' => 'La ciudad no puede tener más de 255 caracteres.',
            'country.max' => 'El país no puede tener más de 255 caracteres.',
            'latitude.numeric' => 'La latitud debe ser un número.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.numeric' => 'La longitud debe ser un número.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'contact_name.max' => 'El nombre de contacto no puede tener más de 255 caracteres.',
            'phone.max' => 'El teléfono no puede tener más de 50 caracteres.',
        ];
    }
}
