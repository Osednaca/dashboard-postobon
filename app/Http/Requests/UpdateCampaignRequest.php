<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCampaignMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCampaignRequest extends FormRequest
{
    use ValidatesCampaignMedia;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCampaignMedia();
        if ($this->boolean('is_permanent')) {
            $this->merge(['end_date' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'in:draft,active,paused,finished'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'is_permanent' => ['sometimes', 'boolean'],
            'end_date' => [Rule::requiredIf($this->has('is_permanent') && ! $this->boolean('is_permanent')),
                'nullable', 'date'],
            'segment_cities' => ['nullable', 'array'],
            'segment_groups' => ['nullable', 'array'],
            'created_by' => ['sometimes', 'exists:users,id'],
        ] + $this->campaignMediaRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }
            $campaign = $this->route('campaign');
            $start = $this->input('start_date', $campaign?->start_date);
            $end = $this->input('end_date', $campaign?->end_date);
            if ($start !== null && $end !== null && Carbon::parse($end)->lt(Carbon::parse($start))) {
                $validator->errors()->add('end_date', 'La fecha de fin debe ser igual o posterior a la fecha de inicio.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 2000 caracteres.',
            'status.in' => 'El estado seleccionado no es válido.',
            'priority.integer' => 'La prioridad debe ser un número entero.',
            'start_date.date' => 'La fecha de inicio no tiene un formato válido.',
            'end_date.date' => 'La fecha de fin no tiene un formato válido.',
            'end_date.required' => 'Indica la fecha de fin o marca la campaña como permanente.',
            'is_permanent.boolean' => 'La opción de campaña permanente no es válida.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'segment_cities.array' => 'Las ciudades de segmentación deben ser un arreglo.',
            'segment_groups.array' => 'Los grupos de segmentación deben ser un arreglo.',
            'created_by.exists' => 'El usuario creador no existe.',
        ] + $this->campaignMediaMessages();
    }
}
