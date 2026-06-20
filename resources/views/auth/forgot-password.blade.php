@extends('layouts.guest')

@section('content')
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">Recuperar contraseña</h1>
        <p class="mt-2 text-sm text-text-muted leading-relaxed">
            Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 p-4 rounded-lg bg-success/10 border border-success/20">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-success mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm text-success">{{ session('status') }}</p>
            </div>
        </div>
    @endif

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

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-text mb-1.5">Correo electrónico</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                class="w-full px-3.5 py-2.5 rounded-lg border border-border bg-surface text-sm text-text placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                placeholder="tu@correo.com">
        </div>

        <button type="submit"
            class="w-full flex items-center justify-center px-4 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 transition-all">
            Enviar enlace de recuperación
        </button>
    </form>

    <div class="mt-6 text-center">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-text-light hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Volver al inicio de sesión
        </a>
    </div>
@endsection
