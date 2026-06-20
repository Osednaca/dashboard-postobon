<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'original_name' => ['sometimes', 'string', 'max:255'],
            'file_path' => ['sometimes', 'string', 'max:500'],
            'mime_type' => ['sometimes', 'string', 'max:255'],
            'size' => ['sometimes', 'integer', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'original_name.max' => 'El nombre original no puede tener más de 255 caracteres.',
            'file_path.max' => 'La ruta del archivo no puede tener más de 500 caracteres.',
            'mime_type.max' => 'El tipo MIME no puede tener más de 255 caracteres.',
            'size.integer' => 'El tamaño debe ser un número entero.',
            'duration.integer' => 'La duración debe ser un número entero.',
            'thumbnail.max' => 'La ruta de la miniatura no puede tener más de 500 caracteres.',
        ];
    }
}
