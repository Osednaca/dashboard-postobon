<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstantPlayMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', 'exists:media,id'],
            'targets' => ['required', 'array', 'min:1', 'max:10000'],
            'targets.*' => [
                'required',
                'string',
                'distinct',
                'max:160',
                'regex:/^(wl35|z2):[A-Za-z0-9_.:-]+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'media_id.required' => 'Selecciona un video de la biblioteca.',
            'media_id.exists' => 'El video seleccionado ya no existe en la biblioteca.',
            'targets.required' => 'Selecciona al menos un ventilador.',
            'targets.min' => 'Selecciona al menos un ventilador.',
            'targets.max' => 'Una reproducción puede incluir máximo 10.000 ventiladores.',
            'targets.*.regex' => 'Uno de los ventiladores seleccionados tiene un identificador inválido.',
        ];
    }
}
