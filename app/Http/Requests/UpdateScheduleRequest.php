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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:power_on,power_off,change_content,activate_campaign,format_sd'],
            'target_scope' => ['required', 'in:all,device,group'],
            'device_id' => ['nullable', 'required_if:target_scope,device', 'exists:devices,id'],
            'group_id' => ['nullable', 'required_if:target_scope,group', 'exists:groups,id'],
            'campaign_id' => ['nullable', 'required_if:type,activate_campaign', 'exists:campaigns,id'],
            'content_id' => ['nullable', 'required_if:type,change_content', 'exists:media,id'],
            'recurrence_type' => ['required', 'in:once,daily,weekly,monthly'],
            'scheduled_at' => ['nullable', 'required_if:recurrence_type,once', 'date'],
            'starts_on' => ['nullable', 'required_unless:recurrence_type,once', 'date'],
            'recurrence_time' => ['nullable', 'required_unless:recurrence_type,once', 'date_format:H:i'],
            'recurrence_days' => ['nullable', 'required_if:recurrence_type,weekly', 'array', 'min:1'],
            'recurrence_days.*' => ['integer', 'between:1,7', 'distinct'],
            'recurrence_day' => ['nullable', 'required_if:recurrence_type,monthly', 'integer', 'between:1,31'],
            'recurrence_ends_at' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', 'in:pending,executed,failed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $schedule = $this->route('schedule');
        $defaultScope = $schedule?->device_id ? 'device' : ($schedule?->group_id ? 'group' : 'all');
        $scope = $this->input('target_scope', $defaultScope);

        $this->merge([
            'name' => $this->input('name', $schedule?->name),
            'type' => $this->input('type', $schedule?->type),
            'target_scope' => $scope,
            'recurrence_type' => $this->input('recurrence_type', $schedule?->recurrence_type ?? 'once'),
            'device_id' => $scope === 'device' ? $this->input('device_id', $schedule?->device_id) : null,
            'group_id' => $scope === 'group' ? $this->input('group_id', $schedule?->group_id) : null,
            'campaign_id' => $this->input('campaign_id', $schedule?->campaign_id),
            'content_id' => $this->input('content_id', $schedule?->content_id),
            'scheduled_at' => $this->input('scheduled_at', $schedule?->scheduled_at?->format('Y-m-d H:i:s')),
            'starts_on' => $this->input('starts_on', $schedule?->scheduled_at?->format('Y-m-d')),
            'recurrence_time' => $this->input('recurrence_time', $schedule?->recurrence_time ? substr($schedule->recurrence_time, 0, 5) : null),
            'recurrence_days' => $this->input('recurrence_days', $schedule?->recurrence_days),
            'recurrence_day' => $this->input('recurrence_day', $schedule?->recurrence_day),
            'recurrence_ends_at' => $this->input('recurrence_ends_at', $schedule?->recurrence_ends_at?->format('Y-m-d')),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'type.in' => 'El tipo seleccionado no es válido.',
            'device_id.exists' => 'El dispositivo seleccionado no existe.',
            'device_id.required_if' => 'Selecciona el dispositivo que recibirá la instrucción.',
            'group_id.exists' => 'El grupo seleccionado no existe.',
            'group_id.required_if' => 'Selecciona el grupo que recibirá la instrucción.',
            'campaign_id.exists' => 'La campaña seleccionada no existe.',
            'campaign_id.required_if' => 'Selecciona la campaña que se debe activar.',
            'content_id.exists' => 'El contenido seleccionado no existe.',
            'content_id.required_if' => 'Selecciona el contenido que se debe reproducir.',
            'scheduled_at.required' => 'La fecha programada es obligatoria.',
            'scheduled_at.date' => 'La fecha programada no tiene un formato válido.',
            'starts_on.required_unless' => 'La fecha de inicio es obligatoria para una programación recurrente.',
            'recurrence_type.required' => 'Selecciona la frecuencia de la programación.',
            'recurrence_type.in' => 'La frecuencia seleccionada no es válida.',
            'recurrence_time.required_unless' => 'La hora de ejecución es obligatoria.',
            'recurrence_time.date_format' => 'La hora de ejecución no tiene un formato válido.',
            'recurrence_days.required_if' => 'Selecciona al menos un día de la semana.',
            'recurrence_days.min' => 'Selecciona al menos un día de la semana.',
            'recurrence_day.required_if' => 'Indica el día del mes.',
            'recurrence_day.between' => 'El día del mes debe estar entre 1 y 31.',
            'recurrence_ends_at.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha de inicio.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
