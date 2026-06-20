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
<body class="min-h-screen flex items-center justify-center bg-white relative overflow-hidden">
    {{-- Subtle gradient background --}}
    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-secondary/10 to-accent/5 pointer-events-none"></div>
    <div class="absolute top-0 left-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-secondary/5 rounded-full blur-3xl translate-x-1/2 translate-y-1/2"></div>

    <div class="relative z-10 w-full max-w-md px-4">
        {{-- Logo --}}
        <div class="flex flex-col items-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-primary flex items-center justify-center shadow-lg shadow-primary/20 mb-4">
                <span class="text-white font-bold text-2xl">3D</span>
            </div>
            <h1 class="text-2xl font-bold text-text tracking-tight">Fan Dashboard</h1>
            <p class="text-sm text-text-muted mt-1">Gestión inteligente de ventiladores 3D</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-xl shadow-black/5 border border-border p-8">
            @if (session('status'))
                <div class="mb-4 p-4 rounded-lg bg-info/10 border border-info/20 text-sm text-info">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </div>

        {{-- Footer --}}
        <div class="mt-8 text-center">
            <p class="text-xs text-text-muted">© {{ date('Y') }} Postobón. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
