<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive,expired'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'alert_days_before' => ['required', 'integer', 'min:0'],
            'restrictions' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la suscripción es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio no tiene un formato válido.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.date' => 'La fecha de fin no tiene un formato válido.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'alert_days_before.required' => 'Los días de alerta son obligatorios.',
            'alert_days_before.integer' => 'Los días de alerta deben ser un número entero.',
            'restrictions.array' => 'Las restricciones deben ser un arreglo.',
        ];
    }
}
