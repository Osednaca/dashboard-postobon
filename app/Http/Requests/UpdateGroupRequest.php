<?php

namespace App\Http\Requests;

use App\Services\GroupCreationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('group')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'device_keys' => ['sometimes', 'array', 'max:10000'],
            'device_keys.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('device_keys')) {
                return;
            }

            $available = array_column(app(GroupCreationService::class)->devices(), 'key');
            foreach ($this->input('device_keys', []) as $key) {
                if (! in_array($key, $available, true)) {
                    $validator->errors()->add('device_keys', 'Selecciona únicamente dispositivos existentes.');
                    break;
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 1000 caracteres.',
        ];
    }
}
