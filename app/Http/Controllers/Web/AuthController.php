<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Handle a login request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        try {
            $credentials = $request->validated();

            if (Auth::attempt($credentials, $request->boolean('remember'))) {
                $request->session()->regenerate();

                return redirect()->route('dashboard.index')
                    ->with('success', 'Inicio de sesión exitoso.');
            }

            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ])->onlyInput('email');
        } catch (\Exception $e) {
            Log::error('Error en login: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al iniciar sesión. Por favor intente nuevamente.');
        }
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        try {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('success', 'Sesión cerrada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error en logout: ' . $e->getMessage());

            return redirect()->route('login')
                ->with('error', 'Ocurrió un error al cerrar sesión.');
        }
    }

    /**
     * Show the forgot password form.
     */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link.
     */
    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            return $status === Password::RESET_LINK_SENT
                ? back()->with('success', 'Se ha enviado un enlace de restablecimiento de contraseña a su correo electrónico.')
                : back()->withErrors(['email' => __($status)]);
        } catch (\Exception $e) {
            Log::error('Error al enviar enlace de restablecimiento: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al enviar el enlace. Por favor intente nuevamente.');
        }
    }

    /**
     * Show the reset password form.
     */
    public function showResetPassword(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->input('email'),
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        try {
            $status = Password::reset(
                $request->validated(),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => bcrypt($password),
                    ])->save();
                }
            );

            return $status === Password::PASSWORD_RESET
                ? redirect()->route('login')->with('success', 'Su contraseña ha sido restablecida exitosamente.')
                : back()->withErrors(['email' => [__($status)]]);
        } catch (\Exception $e) {
            Log::error('Error al restablecer contraseña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al restablecer la contraseña. Por favor intente nuevamente.');
        }
    }
}
