<?php

namespace App\Http\Requests\Mobile;

use App\Services\Mobile\OnboardingTelemetry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterDeviceOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->assignsDevices() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('expected_identifier'))) {
            $this->merge(['expected_identifier' => OnboardingTelemetry::normalize($this->input('expected_identifier'))]);
        }
    }

    public function rules(): array
    {
        return ['expected_identifier' => ['required', 'string', 'regex:/\A[A-F0-9]{12}\z/']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['expected_identifier']) as $field) {
                $validator->errors()->add($field, 'El registro solo acepta la MAC detectada; no acepta configuración ni pruebas de conexión.');
            }
        });
    }
}
