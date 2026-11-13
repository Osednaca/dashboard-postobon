<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

class AuditExportRequest extends AuditIndexRequest
{
    public function rules(): array
    {
        return Arr::except(parent::rules(), 'page') + [
            'format' => ['required', 'string', 'in:pdf,xlsx'],
        ];
    }
}
