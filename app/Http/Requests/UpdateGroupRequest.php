<?php

namespace App\Http\Requests;

use App\Services\GroupCreationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('groups', 'name')->whereNull('deleted_at')->ignore($this->route('group'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'device_keys' => ['sometimes', 'array', 'max:10000'],
            'device_keys.*' => ['required', 'string', 'max:255'],
            'remove_device_keys' => ['sometimes', 'array', 'max:10000'],
            'remove_device_keys.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || (! $this->has('device_keys') && ! $this->has('remove_device_keys'))) {
                return;
            }

            $available = array_column(app(GroupCreationService::class)->devices(), 'key');
            foreach (['device_keys', 'remove_device_keys'] as $field) {
                foreach ($this->input($field, []) as $key) {
                    if (! in_array($key, $available, true)) {
                        $validator->errors()->add($field, 'Selecciona únicamente dispositivos existentes.');
                        break;
                    }
                }
            }
            if (array_intersect($this->input('device_keys', []), $this->input('remove_device_keys', []))) {
                $validator->errors()->add('remove_device_keys', 'Un dispositivo no se puede añadir y quitar a la vez.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe un grupo con este nombre.',
            'description.max' => 'La descripción no puede tener más de 1000 caracteres.',
        ];
    }
}
