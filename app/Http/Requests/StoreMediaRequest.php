<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxUploadBytes = $this->parseUploadSize(ini_get('upload_max_filesize'));
        $maxKb = (int) ($maxUploadBytes / 1024);

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:mp4,avi,mov,wmv,flv,mkv,mp3,wav', 'max:' . $maxKb],
            'duration' => ['nullable', 'integer', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function parseUploadSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;
        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El archivo debe ser un archivo válido.',
            'file.mimes' => 'El archivo debe ser un video o audio en formato: mp4, avi, mov, wmv, flv, mkv, mp3 o wav.',
            'file.max' => 'El archivo no puede pesar más de 500 MB.',
            'file.uploaded' => 'El archivo no se pudo subir. Verifica que el tamaño no exceda el límite del servidor (máximo ' . ini_get('upload_max_filesize') . ').',
            'duration.integer' => 'La duración debe ser un número entero.',
            'thumbnail.max' => 'La ruta de la miniatura no puede tener más de 500 caracteres.',
        ];
    }
}
