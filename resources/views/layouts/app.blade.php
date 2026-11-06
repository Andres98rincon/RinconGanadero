<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#183d32">
    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48" type="image/x-icon">
    <link rel="icon" href="{{ asset('images/rincon-ganadero.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon.png') }}">
    <title>@yield('title', 'Inventario ganadero') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="{{ route('dashboard') }}">
                <img class="brand-mark" src="{{ asset('images/rincon-ganadero.png') }}" alt="Logo de Rincón Ganadero con ganado Gyr y las iniciales AR">
                <span>
                    <span class="brand-name">Rincón Ganadero</span>
                    <span class="brand-caption">Cuaderno de campo</span>
                </span>
            </a>
            <nav aria-label="Navegacion principal">
                <div class="nav-label">Gestion</div>
                @can('dashboard.view')
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg> Panel general
                </a>
                @endcan
                @can('animals.view')
                <a class="nav-link {{ request()->routeIs('animals.*') ? 'active' : '' }}" href="{{ route('animals.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 8 4 5 2 7l2 5v4a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-4l2-5-2-2-3 3z"/><path d="M9 12h.01M15 12h.01M9 16h6"/></svg> Inventario animal
                </a>
                @endcan
                @can('farms.view')
                <a class="nav-link {{ request()->routeIs('farms.*') ? 'active' : '' }}" href="{{ route('farms.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 21V9l9-6 9 6v12"/><path d="M7 21v-8h10v8M10 13v3m4-3v3M2 21h20"/></svg> Fincas
                </a>
                @endcan
                @can('paddocks.view')
                <a class="nav-link {{ request()->routeIs('paddocks.*') ? 'active' : '' }}" href="{{ route('paddocks.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18m6-18v18"/></svg> Potreros
                </a>
                @endcan
                @can('animal-movements.view')
                <a class="nav-link {{ request()->routeIs('animal-movements.*') ? 'active' : '' }}" href="{{ route('animal-movements.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 7h14m0 0-4-4m4 4-4 4M17 17H3m0 0 4 4m-4-4 4-4"/></svg> Compras, ventas y traslados
                </a>
                @endcan
                @can('reproduction.view')
                <a class="nav-link {{ request()->routeIs('reproduction.*') ? 'active' : '' }}" href="{{ route('reproduction.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg> Reproducción
                </a>
                @endcan
                @can('milk-production.view')
                <a class="nav-link {{ request()->routeIs('milk-production.*', 'animals.production.*') ? 'active' : '' }}" href="{{ route('milk-production.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.5S5 10 5 15a7 7 0 0 0 14 0c0-5-7-12.5-7-12.5Z"/><path d="M9 16a3 3 0 0 0 3 3"/></svg> Producción de leche
                </a>
                @endcan
                @can('weights.view')
                <a class="nav-link {{ request()->routeIs('weights.*') ? 'active' : '' }}" href="{{ route('weights.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 17 9 11l4 4 8-9"/><path d="M15 6h6v6M4 21h16"/></svg> Pesajes y crecimiento
                </a>
                @endcan
                @can('finance.view')
                <a class="nav-link {{ request()->routeIs('finance.*') ? 'active' : '' }}" href="{{ route('finance.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><ellipse cx="12" cy="12" rx="9" ry="9"/><path d="M15.5 8.5c-.7-.7-1.8-1-3.1-1-1.7 0-2.9.8-2.9 2s1 1.8 2.8 2.2l.8.2c1.8.4 2.9 1 2.9 2.3s-1.3 2.3-3.3 2.3c-1.4 0-2.7-.5-3.5-1.3M12 5.5v13"/></svg> Finanzas y costos
                </a>
                @endcan
                @can('reports.view')
                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3h10l4 4v14H5z"/><path d="M15 3v5h5M8 13h8m-8 4h8"/></svg> Reportes
                </a>
                @endcan
                @can('users.view')
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 8v6m3-3h-6"/></svg> Usuarios
                </a>
                @endcan
                @can('roles.view')
                <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3 8 4v5c0 5-3.4 8-8 9-4.6-1-8-4-8-9V7z"/><path d="m9 12 2 2 4-4"/></svg> Roles
                </a>
                @endcan
                @can('permissions.manage')
                <a class="nav-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" href="{{ route('admin.permissions.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 14a5 5 0 1 1 4.9-6H22v4h-3v3h-3v-3h-4.1A5 5 0 0 1 7 14Z"/><circle cx="7" cy="9" r="1"/></svg> Permisos
                </a>
                @endcan
                @can('activity-log.view')
                <a class="nav-link {{ request()->routeIs('admin.activity-log.*') ? 'active' : '' }}" href="{{ route('admin.activity-log.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 12h4l3-8 4 16 3-8h4"/><circle cx="12" cy="12" r="10"/></svg> Bitácora
                </a>
                @endcan
                @if (auth()->user()->hasRole(\App\AccessControl::SUPER_ADMIN_ROLE) && auth()->user()->can('backups.manage'))
                <a class="nav-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}" href="{{ route('admin.backups.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3v12m-5-5 5 5 5-5"/><path d="M4 17v4h16v-4"/></svg> Copias de seguridad
                </a>
                @endif
                <button class="nav-link" type="button" data-pwa-install>
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3v12m-5-5 5 5 5-5"/><path d="M4 17v4h16v-4"/></svg> Instalar aplicación
                </button>
            </nav>
            <div class="sidebar-foot">Registro de finca<br>Control claro, cada dia.</div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <span class="topbar-title">Administración de finca <span aria-hidden="true">/</span> Ganadería</span>
                <div class="topbar-account">
                    <a class="topbar-profile" href="{{ route('profile.edit') }}" aria-label="Editar perfil de {{ auth()->user()->name }}">
                        @if (auth()->user()->hasMedia('profile-photo'))
                            <img class="topbar-avatar" src="{{ auth()->user()->getFirstMedia('profile-photo')?->getAvailableUrl(['optimized']) }}" alt="">
                        @else
                            <span class="topbar-avatar topbar-avatar-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                        <span class="topbar-status"><span class="status-dot"></span> {{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="button button-secondary button-small" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </header>
            <main class="main-content">
                @if (session('success'))
                    <div class="flash" data-flash data-swal-toast="{{ session('success') }}" role="status">
                        <span>{{ session('success') }}</span>
                        <button class="flash-close" type="button" data-dismiss aria-label="Cerrar">&times;</button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="flash flash-error" data-flash data-swal-error="{{ session('error') }}" role="alert">
                        <span>{{ session('error') }}</span>
                        <button class="flash-close" type="button" data-dismiss aria-label="Cerrar">&times;</button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="notice notice-error" role="alert">
                        @foreach ($errors->all() as $error)
                            <div data-validation-error>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
