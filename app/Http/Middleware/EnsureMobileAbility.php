<?php

namespace App\Http\Middleware;

use App\Support\MobileApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! $request->user()?->tokenCan($ability)) {
            return MobileApi::error('forbidden', 'Tu sesión no permite esta acción.', 403);
        }

        return $next($request);
    }
}
