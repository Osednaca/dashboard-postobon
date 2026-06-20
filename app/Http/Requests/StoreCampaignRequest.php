<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'created_by' => $this->created_by ?? auth()->id(),
            'status' => $this->status ?? 'draft',
            'priority' => $this->priority ?? 5,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:draft,active,paused,finished'],
            'priority' => ['required', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'segment_cities' => ['nullable', 'array'],
            'segment_groups' => ['nullable', 'array'],
            'created_by' => ['required', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la campaña es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 2000 caracteres.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'priority.required' => 'La prioridad es obligatoria.',
            'priority.integer' => 'La prioridad debe ser un número entero.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio no tiene un formato válido.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.date' => 'La fecha de fin no tiene un formato válido.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'segment_cities.array' => 'Las ciudades de segmentación deben ser un arreglo.',
            'segment_groups.array' => 'Los grupos de segmentación deben ser un arreglo.',
            'created_by.required' => 'El creador es obligatorio.',
            'created_by.exists' => 'El usuario creador no existe.',
        ];
    }
}
