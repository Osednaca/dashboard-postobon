<?php

namespace App\Http\Requests;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignDeviceEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignEstablishment', Device::class) ?? false;
    }

    public function rules(): array
    {
        return ['establishment_id' => ['required', 'integer', 'exists:establishments,id']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['establishment_id', '_token', '_method']) as $field) {
                $validator->errors()->add($field, 'Solo se permite vincular el establecimiento.');
            }
        });
    }
}
