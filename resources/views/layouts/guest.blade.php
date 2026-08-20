<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Autenticación') - 3D Fan Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-surface relative overflow-hidden font-sans antialiased">
    {{-- Postobon Brand Ambient Glows --}}
    <div class="absolute inset-0 bg-gradient-to-br from-primary/8 via-surface to-secondary/8 pointer-events-none"></div>
    <div class="absolute top-0 left-0 w-[500px] h-[500px] bg-primary/10 rounded-full blur-[100px] -translate-x-1/2 -translate-y-1/2 pointer-events-none"></div>
    <div class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-secondary/10 rounded-full blur-[100px] translate-x-1/2 translate-y-1/2 pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-md px-4 py-8">
        {{-- Postobon Official Logo --}}
        <div class="flex flex-col items-center mb-8 text-center">
            <a href="{{ route('login') }}" class="inline-flex items-center justify-center mb-4 transition-transform hover:scale-105">
                <img src="{{ asset('logo_postobon.png') }}" alt="Postobón" class="h-12 w-auto object-contain">
            </a>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-xl shadow-primary/5 border border-border p-8">
            @if (session('status'))
                <div class="mb-4 p-4 rounded-lg bg-info/10 border border-info/20 text-sm text-info">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </div>

        {{-- Footer --}}
        <div class="mt-8 text-center">
            <p class="text-xs text-text-muted">© {{ date('Y') }} Postobón S.A. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
