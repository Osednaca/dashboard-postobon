<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class LocationPickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->managesEstablishments() ?? false;
    }

    public function rules(): array
    {
        return [
            'nonce' => ['required', 'uuid'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }
}
