<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:power_on,power_off,change_content,activate_campaign'],
            'device_id' => ['nullable', 'exists:devices,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'content_id' => ['nullable', 'exists:media,id'],
            'scheduled_at' => ['required', 'date'],
            'status' => ['sometimes', 'in:pending,executed,failed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la programación es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'type.required' => 'El tipo es obligatorio.',
            'type.in' => 'El tipo seleccionado no es válido.',
            'device_id.exists' => 'El dispositivo seleccionado no existe.',
            'group_id.exists' => 'El grupo seleccionado no existe.',
            'campaign_id.exists' => 'La campaña seleccionada no existe.',
            'content_id.exists' => 'El contenido seleccionado no existe.',
            'scheduled_at.required' => 'La fecha programada es obligatoria.',
            'scheduled_at.date' => 'La fecha programada no tiene un formato válido.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
