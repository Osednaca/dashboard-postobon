<?php

namespace App\Http\Requests\Mobile;

use App\Services\Mobile\OnboardingTelemetry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DeviceOnboardingRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $rules = [
            'name' => [$required, 'string', 'max:64', 'not_regex:/[&=\x00-\x1F\x7F]/'],
            'establishment_id' => [$required, 'integer', 'exists:establishments,id'],
            'play_mode' => [$required, 'integer', Rule::in([0, 1])],
            'sync_class' => [$required, 'integer', 'between:0,14'],
        ];
        if ($this->isMethod('POST')) {
            $rules['device_type'] = ['required', Rule::in(['z2'])];
            $rules['expected_identifier'] = ['required', 'string', 'regex:/\A[A-F0-9]{12}\z/'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Este campo no forma parte del registro del dispositivo.');
            }
            if (is_string($this->input('name')) && strlen($this->input('name')) > 128) {
                $validator->errors()->add('name', 'El nombre no puede superar 128 bytes UTF-8.');
            }
            if ($this->isMethod('POST')) {
                if (! preg_match('/\A[A-Za-z0-9._:-]{8,100}\z/', (string) $this->header('Idempotency-Key', ''))) {
                    $validator->errors()->add('idempotency_key', 'Envía una clave de operación válida de 8 a 100 caracteres.');
                }
                if (in_array($this->input('play_mode'), [0, '0'], true) && ! in_array($this->input('sync_class'), [0, '0'], true)) {
                    $validator->errors()->add('sync_class', 'El modo individual utiliza la clase A (0).');
                }
            }
        });
    }
}
