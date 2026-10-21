<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FirmwareCloudDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('Indica un host DNS o IPv4 y un puerto entre 1 y 65535, sin URL, ruta ni credenciales.');
        }
    }

    public static function normalize(string $value): ?string
    {
        if (strlen($value) > 255 || ! preg_match('/\A([^:]+):([0-9]{1,5})\z/', $value, $match)) {
            return null;
        }
        [$host, $port] = [$match[1], (int) $match[2]];
        if ($port < 1 || $port > 65535 || strlen($host) > 253) {
            return null;
        }
        if (preg_match('/\A[0-9.]+\z/', $host)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return null;
            }
        } else {
            foreach (explode('.', $host) as $label) {
                if (! preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/i', $label)) {
                    return null;
                }
            }
        }

        return strtolower($host).':'.$port;
    }
}
