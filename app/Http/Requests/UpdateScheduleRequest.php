<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:power_on,power_off,content_change'],
            'device_id' => ['nullable', 'exists:devices,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'scheduled_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:pending,executed,failed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'type.in' => 'El tipo seleccionado no es válido.',
            'device_id.exists' => 'El dispositivo seleccionado no existe.',
            'group_id.exists' => 'El grupo seleccionado no existe.',
            'campaign_id.exists' => 'La campaña seleccionada no existe.',
            'scheduled_at.date' => 'La fecha programada no tiene un formato válido.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
