<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive,expired'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'alert_days_before' => ['sometimes', 'integer', 'min:0'],
            'restrictions' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'status.in' => 'El estado seleccionado no es válido.',
            'start_date.date' => 'La fecha de inicio no tiene un formato válido.',
            'end_date.date' => 'La fecha de fin no tiene un formato válido.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'alert_days_before.integer' => 'Los días de alerta deben ser un número entero.',
            'restrictions.array' => 'Las restricciones deben ser un arreglo.',
        ];
    }
}
