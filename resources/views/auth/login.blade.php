@extends('layouts.guest')

@section('content')
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">Iniciar sesión</h1>
        <p class="mt-1 text-sm text-text-muted">Ingresa tus credenciales para acceder al panel</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-danger/10 border border-danger/20">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-danger mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="text-sm font-medium text-danger">Por favor corrige los siguientes errores:</p>
                    <ul class="mt-1 text-sm text-danger/80 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-text mb-1.5">Correo electrónico</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                class="w-full px-3.5 py-2.5 rounded-lg border border-border bg-surface text-sm text-text placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                placeholder="tu@correo.com">
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="block text-sm font-medium text-text mb-1.5">Contraseña</label>
            <div class="relative">
                <input :type="show ? 'text' : 'password'" name="password" id="password" required
                    class="w-full px-3.5 py-2.5 pr-10 rounded-lg border border-border bg-surface text-sm text-text placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                    placeholder="••••••••">
                <button type="button" @click="show = !show"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-text-muted hover:text-text transition-colors">
                    <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    <svg x-show="show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.564-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.05 10.05 0 01-3.929 5.292"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="w-4 h-4 rounded border-border text-primary focus:ring-primary/20">
                <span class="text-sm text-text-light">Recordarme</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:text-primary/80 transition-colors">
                ¿Olvidaste tu contraseña?
            </a>
        </div>

        <button type="submit"
            class="w-full flex items-center justify-center px-4 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 transition-all">
            Iniciar sesión
        </button>
    </form>
@endsection
