<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DeviceOnboardingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->assignsDevices() ?? false;
    }

    public function rules(): array
    {
        return [
            'event' => ['required', Rule::in(['local_connected', 'configuration_sent'])],
            'result_code' => ['sometimes', Rule::in(['sent', 'readback_verified', 'connection_failed', 'response_unknown'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'No se permiten payloads ni contraseñas en los eventos.');
            }
        });
    }
}
