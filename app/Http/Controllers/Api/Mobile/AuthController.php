<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MobileForgotPasswordRequest;
use App\Http\Requests\Mobile\MobileLoginRequest;
use App\Models\User;
use App\Support\MobileApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(MobileLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return MobileApi::error('invalid_credentials', 'El correo o la contraseña son incorrectos.', 401);
        }
        if (! in_array($user->role, ['tecnico', 'superadmin'], true)) {
            return MobileApi::error('forbidden', 'Tu cuenta no tiene acceso a la aplicación móvil.', 403);
        }
        $expires = now()->addDays(max(1, (int) config('mobile.token_days', 7)));
        $token = $user->createToken('mobile:'.($data['device_name'] ?? 'Postobon App'), MobileApi::ABILITIES, $expires);

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expires->toIso8601String(),
            'user' => MobileApi::user($user),
        ]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => MobileApi::user($request->user())]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function forgotPassword(MobileForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return response()->json(['data' => ['message' => 'Si el correo está registrado, recibirás un enlace de recuperación.']], 202);
    }
}
