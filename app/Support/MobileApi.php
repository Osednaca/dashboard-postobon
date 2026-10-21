<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class MobileApi
{
    public const ABILITIES = ['mobile:session', 'devices:read', 'establishments:manage', 'business-types:manage', 'onboarding:create'];

    public static function error(string $code, string $message, int $status = 422, array $fields = []): JsonResponse
    {
        return response()->json(['error' => [
            'code' => $code,
            'message' => $message,
            'fields' => (object) $fields,
            'request_id' => (string) Str::uuid(),
        ]], $status);
    }

    public static function user(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role']);
    }

    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/mobile/v1', 'api/mobile/v1/*')) {
            return null;
        }
        if ($exception instanceof HttpResponseException) {
            return null;
        }
        if ($exception instanceof ValidationException) {
            return self::error('validation_failed', 'Revisa los campos indicados.', 422, $exception->errors());
        }
        $status = match (true) {
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
        [$code, $message] = match ($status) {
            401 => ['unauthenticated', 'Inicia sesión para continuar.'],
            403 => ['forbidden', 'No tienes permiso para realizar esta acción.'],
            404 => ['not_found', 'El recurso solicitado no existe.'],
            405 => ['method_not_allowed', 'Esta acción no está disponible.'],
            409 => ['conflict', 'La operación tiene un conflicto.'],
            429 => ['rate_limited', 'Demasiados intentos. Intenta más tarde.'],
            default => ['server_error', 'No fue posible completar la operación.'],
        };
        $response = self::error($code, $message, $status);
        if ($exception instanceof HttpExceptionInterface) {
            $response->headers->add($exception->getHeaders());
        }

        return $response;
    }
}
