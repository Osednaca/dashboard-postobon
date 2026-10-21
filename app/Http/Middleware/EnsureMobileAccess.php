<?php

namespace App\Http\Middleware;

use App\Support\MobileApi;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if (! $request->bearerToken() || ! $token instanceof PersonalAccessToken
            || ! str_starts_with($token->name, 'mobile:')
            || ! in_array('mobile:session', $token->abilities ?? [], true)
            || in_array('*', $token->abilities ?? [], true)) {
            return MobileApi::error('mobile_token_required', 'Usa una sesión de la aplicación móvil.', 403);
        }
        if (! in_array($user->role, ['tecnico', 'superadmin'], true)) {
            return MobileApi::error('forbidden', 'Tu cuenta no tiene acceso a la aplicación móvil.', 403);
        }

        return $next($request);
    }
}
