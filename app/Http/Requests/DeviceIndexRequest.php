<?php

namespace App\Http\Requests;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Device::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->whereNull('deleted_at')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
