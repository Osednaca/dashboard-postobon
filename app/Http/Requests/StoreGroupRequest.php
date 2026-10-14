<?php

namespace App\Http\Requests;

use App\Models\Group;
use App\Services\GroupCreationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Group::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('groups', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'business_type_id' => ['nullable', 'integer', 'exists:business_types,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'device_keys' => ['sometimes', 'array', 'max:10000'],
            'device_keys.*' => ['required', 'string', 'max:255', 'distinct:strict'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
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
            'name.required' => 'El nombre del grupo es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe un grupo con este nombre.',
            'description.max' => 'La descripción no puede tener más de 1000 caracteres.',
            'business_type_id.exists' => 'El tipo de negocio seleccionado no existe.',
            'latitude.required_with' => 'Indica la latitud junto con la longitud.',
            'longitude.required_with' => 'Indica la longitud junto con la latitud.',
            'latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'device_keys.*.distinct' => 'No selecciones un dispositivo más de una vez.',
        ];
    }
}
