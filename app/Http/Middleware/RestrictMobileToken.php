<?php

namespace App\Http\Middleware;

use App\Support\MobileApi;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RestrictMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/mobile/v1', 'api/mobile/v1/*') && $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($request->bearerToken());
            if ($token && (str_starts_with($token->name, 'mobile:') || in_array('mobile:session', $token->abilities ?? [], true))) {
                return MobileApi::error('mobile_token_restricted', 'Este token solo puede utilizar la API móvil.', 403);
            }
        }

        return $next($request);
    }
}
