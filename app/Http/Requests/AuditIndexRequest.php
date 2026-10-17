<?php

namespace App\Http\Requests;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;

class AuditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:255'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d', 'regex:/^(?!0000)\d{4}-\d{2}-\d{2}$/'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
