<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FleetUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'video' => ['nullable', 'required_without:media_id', 'prohibits:media_id', 'file', 'mimes:mp4', 'max:256000'],
            'media_id' => ['nullable', 'required_without:video', 'prohibits:video', 'integer',
                Rule::exists('media', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('mime_type', 'video/mp4'))],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', 'string', 'distinct', 'max:160', 'regex:/^(wl35|z2):[A-Za-z0-9_.:-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required_without' => 'Selecciona un archivo MP4 o un video de la biblioteca.',
            'video.prohibits' => 'Selecciona una sola fuente: archivo o biblioteca.',
            'media_id.required_without' => 'Selecciona un archivo MP4 o un video de la biblioteca.',
            'media_id.prohibits' => 'Selecciona una sola fuente: archivo o biblioteca.',
            'media_id.exists' => 'El video seleccionado no está disponible en la biblioteca MP4.',
            'video.mimes' => 'El archivo debe ser un video MP4.',
            'video.max' => 'El video no puede superar 250 MB.',
            'targets.required' => 'Selecciona al menos un ventilador.',
            'targets.min' => 'Selecciona al menos un ventilador.',
            'targets.*.regex' => 'Uno de los identificadores seleccionados no es válido.',
        ];
    }
}
