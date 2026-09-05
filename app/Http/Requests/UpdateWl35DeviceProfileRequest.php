<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWl35DeviceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'establishment_id' => ['required', 'exists:establishments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del WL35 es obligatorio.',
            'name.max' => 'El nombre no puede superar 255 caracteres.',
            'establishment_id.required' => 'Selecciona el establecimiento del WL35.',
            'establishment_id.exists' => 'El establecimiento seleccionado no existe.',
            'notes.max' => 'Las notas no pueden superar 2000 caracteres.',
        ];
    }
}
