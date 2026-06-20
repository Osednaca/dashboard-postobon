<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'read_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'type.required' => 'El tipo es obligatorio.',
            'type.max' => 'El tipo no puede tener más de 255 caracteres.',
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede tener más de 255 caracteres.',
            'message.required' => 'El mensaje es obligatorio.',
            'message.max' => 'El mensaje no puede tener más de 2000 caracteres.',
            'data.array' => 'Los datos adicionales deben ser un arreglo.',
            'read_at.date' => 'La fecha de lectura no tiene un formato válido.',
        ];
    }
}
