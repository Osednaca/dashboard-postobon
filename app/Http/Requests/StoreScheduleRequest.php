<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Schedule::class)
            && ($this->user()->managesOperations()
                || ($this->input('type') !== 'activate_campaign' && ! $this->filled('campaign_id')));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:power_on,power_off,change_content,format_sd'],
            'target_scope' => ['required', 'in:all,device,group'],
            'device_id' => ['nullable', 'required_if:target_scope,device', 'prohibited_unless:target_scope,device', Rule::exists('devices', 'id')->whereNull('deleted_at')],
            'group_id' => ['nullable', 'required_if:target_scope,group', 'prohibited_unless:target_scope,group', Rule::exists('groups', 'id')->whereNull('deleted_at')],
            'campaign_id' => ['prohibited'],
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
        $defaultScope = $this->filled('device_id') !== $this->filled('group_id')
            ? ($this->filled('device_id') ? 'device' : 'group')
            : ($this->input('type') === 'change_content' && ! $this->filled('device_id') && ! $this->filled('group_id') ? 'all' : null);
        $scope = $this->input('target_scope', $defaultScope);

        $this->merge([
            'target_scope' => $scope,
            'recurrence_type' => $this->input('recurrence_type', 'once'),
            'device_id' => $this->input('device_id'),
            'group_id' => $this->input('group_id'),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la programación es obligatorio.',
            'target_scope.required' => 'Selecciona explícitamente Todos, Dispositivo o Grupo antes de guardar.',
            'target_scope.in' => 'El alcance seleccionado no es válido.',
            'device_id.prohibited_unless' => 'El dispositivo no coincide con el alcance seleccionado.',
            'group_id.prohibited_unless' => 'El grupo no coincide con el alcance seleccionado.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'type.required' => 'El tipo es obligatorio.',
            'type.in' => $this->input('type') === 'activate_campaign'
                ? 'La acción Activar Campaña ya no está disponible. Selecciona otra acción.'
                : 'El tipo seleccionado no es válido.',
            'campaign_id.prohibited' => 'Las programaciones ya no permiten activar campañas.',
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
