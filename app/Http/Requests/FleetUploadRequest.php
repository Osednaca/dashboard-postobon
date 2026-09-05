<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FleetUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'mimes:mp4', 'max:256000'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', 'string', 'distinct', 'max:160', 'regex:/^(wl35|z2):[A-Za-z0-9_.:-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required' => 'Selecciona un video MP4.',
            'video.mimes' => 'El archivo debe ser un video MP4.',
            'video.max' => 'El video no puede superar 250 MB.',
            'targets.required' => 'Selecciona al menos un ventilador.',
            'targets.min' => 'Selecciona al menos un ventilador.',
            'targets.*.regex' => 'Uno de los identificadores seleccionados no es válido.',
        ];
    }
}
