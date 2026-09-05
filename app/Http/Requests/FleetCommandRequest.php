<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FleetCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $command = $this->input('command');
        $targets = $this->input('targets', []);
        $hasWl35 = is_array($targets) && collect($targets)->contains(
            fn ($target): bool => is_string($target) && str_starts_with($target, 'wl35:')
        );
        $hasZ2 = is_array($targets) && collect($targets)->contains(
            fn ($target): bool => is_string($target) && str_starts_with($target, 'z2:')
        );

        return [
            'command' => ['required', Rule::in(['power', 'bluetooth', 'play', 'format_sd'])],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', 'string', 'distinct', 'max:160', 'regex:/^(wl35|z2):[A-Za-z0-9_.:-]+$/'],
            'value' => [Rule::requiredIf(in_array($command, ['power', 'bluetooth'], true)), 'nullable', 'boolean'],
            'wl35_video_index' => [Rule::requiredIf($command === 'play' && $hasWl35), 'nullable', 'integer', 'min:1', 'max:255'],
            'z2_filename' => [Rule::requiredIf($command === 'play' && $hasZ2), 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'targets.required' => 'Selecciona al menos un ventilador.',
            'targets.min' => 'Selecciona al menos un ventilador.',
            'targets.*.regex' => 'Uno de los identificadores seleccionados no es válido.',
            'value.required' => 'Debes indicar el estado de la orden.',
            'wl35_video_index.required' => 'Indica el número de video que reproducirán los WL35.',
            'wl35_video_index.max' => 'El índice de video WL35 debe estar entre 1 y 255.',
            'z2_filename.required' => 'Selecciona el archivo que reproducirán los Z2.',
        ];
    }
}
