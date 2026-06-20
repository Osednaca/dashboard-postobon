<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@if(isset($title) && trim($title) !== ''){{ $title }}@else @yield('title', 'Panel') @endif | {{ config('app.name', '3D Fan Dashboard') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="bg-surface text-text font-sans antialiased min-h-screen" x-data="{ sidebarOpen: false, dropdownOpen: false }">

    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-border transform transition-transform duration-200 lg:translate-x-0 lg:static lg:inset-auto"
               :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="flex items-center gap-3 px-6 py-5 border-b border-border">
                <div class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"></path>
                    </svg>
                </div>
                <span class="text-lg font-bold text-text">3D Fan Dashboard</span>
            </div>

            <nav class="px-3 py-4 space-y-1 overflow-y-auto h-[calc(100vh-80px)]">
                @php
                $navItems = [
                    ['route' => 'dashboard.index', 'label' => 'Dashboard', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
                    ['route' => 'campaigns.index', 'label' => 'Campañas', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                    ['route' => 'instant-play.index', 'label' => 'Reproducción', 'icon' => 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['route' => 'schedules.index', 'label' => 'Programación', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['route' => 'devices.index', 'label' => 'Dispositivos', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ['route' => 'groups.index', 'label' => 'Grupos', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                    ['route' => 'locations.index', 'label' => 'Ubicaciones', 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
                    ['route' => 'media.index', 'label' => 'Medios', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                    ['route' => 'subscriptions.index', 'label' => 'Suscripciones', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['route' => 'notifications.index', 'label' => 'Notificaciones', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
                    ['route' => 'analytics.index', 'label' => 'Analíticas', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['route' => 'audit.index', 'label' => 'Auditoría', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
                    ['route' => 'users.index', 'label' => 'Usuarios', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                ];
                @endphp

                @foreach($navItems as $item)
                    @php $isActive = request()->routeIs($item['route'] . '*'); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $isActive ? 'bg-primary/10 text-primary' : 'text-text-light hover:bg-surface-dark hover:text-text' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- Overlay for mobile sidebar --}}
        <div class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"></div>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- Header --}}
            <header class="sticky top-0 z-30 bg-white border-b border-border px-4 sm:px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg hover:bg-surface-dark text-text-light">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold text-text">@if(isset($title) && trim($title) !== ''){{ $title }}@else @yield('title', 'Panel') @endif</h1>
                </div>

                <div class="relative">
                    <button @click="dropdownOpen = !dropdownOpen" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-surface-dark transition-colors">
                        <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-semibold">
                            {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                        </div>
                        <span class="text-sm font-medium text-text hidden sm:block">{{ auth()->user()->name ?? 'Usuario' }}</span>
                        <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="dropdownOpen" @click.away="dropdownOpen = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-border py-1 z-50">
                        <a href="#" class="block px-4 py-2 text-sm text-text-light hover:bg-surface-dark hover:text-text">Perfil</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-danger hover:bg-surface-dark">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- Main --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
                @if(session('success'))
                    <div class="mb-4 rounded-lg bg-success/10 border border-success/20 px-4 py-3 text-sm text-success" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 rounded-lg bg-danger/10 border border-danger/20 px-4 py-3 text-sm text-danger" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                        {{ session('error') }}
                    </div>
                @endif

                @if(isset($slot) && trim($slot) !== '')
                    {{ $slot }}
                @else
                    @yield('content')
                @endif
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
