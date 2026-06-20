<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a user and return a token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();

            if (! Auth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Las credenciales proporcionadas son incorrectas.',
                ], 401);
            }

            $user = Auth::user();

            if (! $user instanceof User) {
                return response()->json([
                    'message' => 'No se pudo autenticar al usuario.',
                ], 401);
            }

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al iniciar sesión.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Log out the authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Sesión cerrada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cerrar sesión.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'user' => $request->user(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => 'Se ha enviado el enlace de restablecimiento de contraseña.',
                ]);
            }

            return response()->json([
                'message' => 'No se pudo enviar el enlace de restablecimiento.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al enviar el enlace de restablecimiento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset the user password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                    ])->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => 'Contraseña restablecida correctamente.',
                ]);
            }

            return response()->json([
                'message' => 'No se pudo restablecer la contraseña.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al restablecer la contraseña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
