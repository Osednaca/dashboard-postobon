<?php

namespace App\Http\Requests\Concerns;

use App\Models\Device;
use App\Models\Wl35DeviceProfile;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesScheduleDevices
{
    private function scheduleDeviceRules(): array
    {
        return [
            'device_id' => [
                'nullable', Rule::requiredIf(fn () => $this->input('target_scope') === 'device' && ! $this->filled('device_targets')),
                'prohibited_unless:target_scope,device', Rule::exists('devices', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && $this->filled('device_targets')) {
                        $fail('Selecciona los dispositivos en un solo formato.');
                    }
                },
            ],
            'device_targets' => [
                'nullable', 'array', 'min:1', 'prohibited_unless:target_scope,device',
                Rule::requiredIf(fn () => $this->input('target_scope') === 'device' && ! $this->filled('device_id')),
            ],
            'device_targets.*' => ['required', 'string', 'distinct', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! preg_match('/^(z2|wl35):([1-9][0-9]*)$/D', $value, $matches)) {
                    $fail('El dispositivo seleccionado no es válido.');

                    return;
                }
                $exists = $matches[1] === 'z2'
                    ? Device::whereKey($matches[2])->exists()
                    : Wl35DeviceProfile::whereKey($matches[2])->exists();
                if (! $exists) {
                    $fail('El dispositivo seleccionado no existe.');
                }
            }],
        ];
    }
}
