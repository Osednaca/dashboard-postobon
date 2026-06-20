<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkGroupOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['required', 'integer', 'exists:groups,id'],
            'action' => ['required', 'in:power_on,power_off,disable,enable'],
        ];
    }

    public function messages(): array
    {
        return [
            'group_ids.required' => 'Debe seleccionar al menos un grupo.',
            'group_ids.array' => 'Los grupos deben ser un arreglo.',
            'group_ids.min' => 'Debe seleccionar al menos un grupo.',
            'group_ids.*.required' => 'Cada grupo debe tener un ID.',
            'group_ids.*.integer' => 'El ID del grupo debe ser un número entero.',
            'group_ids.*.exists' => 'Uno de los grupos seleccionados no existe.',
            'action.required' => 'La acción es obligatoria.',
            'action.in' => 'La acción seleccionada no es válida.',
        ];
    }
}
