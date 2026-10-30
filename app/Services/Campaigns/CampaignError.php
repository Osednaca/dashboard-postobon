<?php

namespace App\Services\Campaigns;

use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CampaignError
{
    public static function safeStored(?string $message): ?string
    {
        if ($message !== null && preg_match('~https?://|password|passwd|token|secret|authorization|api[_-]?key~i', $message)) {
            return 'No se pudo procesar el dispositivo. Revisa los registros del servidor.';
        }

        return $message;
    }

    public static function publicMessage(Throwable $exception): string
    {
        // Unexpected exceptions can contain database connection strings, URLs or credentials.
        $safe = [
            'Se necesita un reporte reciente del contenido del equipo 42.',
            'El equipo 35 no está disponible para procesar la campaña.',
            'No se confirmó la identidad del archivo cargado en el equipo 35.',
            'El archivo original de la campaña no está disponible.',
            'No se pudo preparar el archivo en la nube 42.',
            'Respuesta de borrado sin evidencia del dispositivo.',
            'No se pudo solicitar el retiro del archivo 42.',
            'No se confirmó la detención o reanudación del dispositivo.',
            'El equipo 42 no aceptó seleccionar el archivo instalado.',
            'El índice 35 requiere revisión antes de reproducir.',
            'El equipo 35 no confirmó la selección.',
            'Espera a que termine la operación de contenido del equipo 42 antes de iniciar la campaña.',
            'El equipo 35 no informó un conteo válido para conservar su contenido.',
            'La selección anterior no está disponible o aún hay un retiro pendiente en el equipo 42.',
            'El contenido del equipo 35 cambió. Revisa la selección anterior antes de recuperarla.',
            'La identidad de la selección anterior del equipo 35 cambió.',
            'El equipo 35 no confirmó la selección anterior.',
        ];

        if ($exception instanceof RuntimeException && in_array($exception->getMessage(), $safe, true)) {
            return $exception->getMessage();
        }

        if ($exception instanceof ValidationException) {
            $validation = collect($exception->errors())->flatten()->first();
            $safeValidation = [
                'La campaña ya terminó. Ajusta su período antes de activarla.',
                'Selecciona al menos un video para activar la campaña.',
                'Selecciona únicamente videos para la campaña.',
                'Selecciona videos con duración conocida para reproducir la secuencia.',
                'Un video seleccionado no está disponible.',
                'Selecciona dispositivos disponibles para la campaña.',
                'Este archivo aún se está retirando. Espera su confirmación antes de activarlo.',
            ];
            if (in_array($validation, $safeValidation, true)) {
                return $validation;
            }
        }

        return 'No se pudo procesar el dispositivo. Revisa los registros del servidor.';
    }
}
