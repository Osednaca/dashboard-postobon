<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetWl35VolumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'volume' => ['required', 'integer', 'between:1,10'],
        ];
    }

    public function messages(): array
    {
        return [
            'volume.required' => 'Selecciona el volumen que se aplicará.',
            'volume.between' => 'El volumen debe estar entre 1 y 10.',
        ];
    }
}
