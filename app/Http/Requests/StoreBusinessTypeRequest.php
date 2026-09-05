<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120', 'unique:business_types,name']];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del tipo de negocio es obligatorio.',
            'name.unique' => 'Ya existe un tipo de negocio con ese nombre.',
        ];
    }
}
