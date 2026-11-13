<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return ['format' => ['required', 'string', 'in:pdf,xlsx']];
    }
}
