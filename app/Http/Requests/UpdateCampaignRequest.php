<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'in:draft,active,paused,finished'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'segment_cities' => ['nullable', 'array'],
            'segment_groups' => ['nullable', 'array'],
            'created_by' => ['sometimes', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 2000 caracteres.',
            'status.in' => 'El estado seleccionado no es válido.',
            'priority.integer' => 'La prioridad debe ser un número entero.',
            'start_date.date' => 'La fecha de inicio no tiene un formato válido.',
            'end_date.date' => 'La fecha de fin no tiene un formato válido.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'segment_cities.array' => 'Las ciudades de segmentación deben ser un arreglo.',
            'segment_groups.array' => 'Los grupos de segmentación deben ser un arreglo.',
            'created_by.exists' => 'El usuario creador no existe.',
        ];
    }
}
