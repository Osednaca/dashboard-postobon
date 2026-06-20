<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'read_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.max' => 'El tipo no puede tener más de 255 caracteres.',
            'title.max' => 'El título no puede tener más de 255 caracteres.',
            'message.max' => 'El mensaje no puede tener más de 2000 caracteres.',
            'data.array' => 'Los datos adicionales deben ser un arreglo.',
            'read_at.date' => 'La fecha de lectura no tiene un formato válido.',
        ];
    }
}
