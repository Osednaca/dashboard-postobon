<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ExceptionDiagnostics
{
    public function report(Throwable $exception): void
    {
        $request = app()->bound('request') ? request() : null;
        $reference = $request?->attributes->get('exception_reference') ?? (string) Str::uuid();
        $request?->attributes->set('exception_reference', $reference);

        // Blade wraps the original error; locate that cause without logging its data.
        $cause = $exception;
        while ($cause->getPrevious() !== null) {
            $cause = $cause->getPrevious();
        }

        Log::channel('diagnostics')->error('Unhandled application exception', [
            'reference' => $reference,
            'exception_class' => $cause::class,
            'file' => $cause->getFile(),
            'line' => $cause->getLine(),
            'route' => $request?->route()?->getName(),
            // Exception messages, trace arguments and request contents may contain credentials.
            'trace' => array_map(static fn (array $frame): array => array_intersect_key($frame, array_flip([
                'file', 'line', 'class', 'function', 'type',
            ])), array_slice($cause->getTrace(), 0, 8)),
        ]);
    }
}
