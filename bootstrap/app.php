<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureMobileAbility;
use App\Http\Middleware\EnsureMobileAccess;
use App\Http\Middleware\RestrictMobileToken;
use App\Support\ExceptionDiagnostics;
use App\Support\MobileApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Wi-Fi credentials are byte-sensitive; retain spaces without disabling
        // ordinary normalization for names, addresses or other request fields.
        $middleware->trimStrings(except: ['wifi_ssid', 'wifi_password']);
        $middleware->trustProxies(at: '*');
        $middleware->redirectUsersTo(fn (Request $request): string => route(
            $request->user()?->role === 'tecnico' ? 'establishments.index' : 'dashboard.index',
        ));

        $middleware->appendToGroup('api', [
            EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->prependToGroup('api', [RestrictMobileToken::class]);

        $middleware->alias([
            'role' => CheckRole::class,
            'mobile.access' => EnsureMobileAccess::class,
            'mobile.ability' => EnsureMobileAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (Throwable $exception, Request $request) => MobileApi::render($exception, $request));
        $exceptions->report(function (Throwable $exception): void {
            app(ExceptionDiagnostics::class)->report($exception);
        });
        $exceptions->respond(function (Response $response) {
            $reference = request()->attributes->get('exception_reference');
            if ($response->getStatusCode() === 500 && is_string($reference)) {
                $response->headers->set('X-Error-Reference', $reference);
            }

            return $response;
        });
    })->create();
