<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:mp4,avi,mov,wmv,flv,mkv', 'max:512000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El archivo debe ser un archivo válido.',
            'file.mimes' => 'El archivo debe ser un video en formato: mp4, avi, mov, wmv, flv o mkv.',
            'file.max' => 'El archivo no puede pesar más de 500 MB.',
        ];
    }
}
