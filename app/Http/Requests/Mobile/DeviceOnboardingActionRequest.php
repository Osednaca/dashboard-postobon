<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeviceOnboardingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->assignsDevices() ?? false;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                $validator->errors()->add($field, 'Esta acción no acepta datos de configuración ni pruebas del cliente.');
            }
        });
    }
}
