<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteWl35VideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'index' => ['required', 'integer', 'between:1,255'],
        ];
    }

    public function messages(): array
    {
        return [
            'index.required' => 'Indica el índice del video que se eliminará.',
            'index.between' => 'El índice debe estar entre 1 y 255.',
        ];
    }
}
