<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkRemoveVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->operatesDevices() ?? false;
    }

    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', Rule::exists('media', 'id')->withoutTrashed()->where(fn ($query) => $query->where('mime_type', 'like', 'video/%'))],
            'device_keys' => ['required', 'array', 'min:1', 'max:100'],
            'device_keys.*' => ['required', 'string', 'distinct', 'max:255', 'regex:/^(z2:[1-9][0-9]*|wl35:[A-Za-z0-9_.:-]+)$/'],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->withoutTrashed()],
        ];
    }
}
