<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#183d32">
    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="offline-sync-url" content="{{ route('offline.sync') }}">
    <meta name="offline-status-url" content="{{ route('offline.status') }}">
    <meta name="offline-user-id" content="{{ auth()->id() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48" type="image/x-icon">
    <link rel="icon" href="{{ asset('images/rincon-ganadero.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script>
        (() => {
            const stored = localStorage.getItem('rincon-ganadero-theme');
            const preference = ['light', 'dark', 'system'].includes(stored) ? stored : 'light';
            const dark = preference === 'dark' || (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.documentElement.dataset.themePreference = preference;
        })();
    </script>
    <title>@yield('title', 'Inventario ganadero') | {{ config('app.name') }}</title>
    <style>
        [data-app-loading] { position: fixed; inset: 0; z-index: 1000; display: grid; place-items: center; background: #17392f; color: #f5f8f1; }
        [data-app-loading] .app-loading-card { display: grid; justify-items: center; gap: 10px; text-align: center; font-family: system-ui, sans-serif; }
        [data-app-loading] .app-loading-spinner { width: 28px; height: 28px; border: 3px solid rgb(255 255 255 / 28%); border-top-color: #f2b178; border-radius: 50%; animation: app-loading-spin .8s linear infinite; }
        @keyframes app-loading-spin { to { transform: rotate(360deg); } }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body>
    <div class="app-loading" data-app-loading role="status" aria-live="polite">
        <div class="app-loading-card">
            <img src="{{ asset('images/rincon-ganadero.png') }}" alt="" class="app-loading-logo">
            <div class="app-loading-spinner" aria-hidden="true"></div>
            <strong>Bienvenido a Rincón Ganadero</strong>
            <span>Preparando tu espacio de trabajo…</span>
        </div>
    </div>
    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar" data-mobile-menu>
            <button type="button" class="sidebar-close" data-mobile-menu-close aria-label="Cerrar menú">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
            <a class="brand" href="{{ route('dashboard') }}">
                <img class="brand-mark" src="{{ asset('images/rincon-ganadero.png') }}" alt="Logo de Rincón Ganadero con ganado Gyr y las iniciales AR">
                <span>
                    <span class="brand-name">Rincón Ganadero</span>
                    <span class="brand-caption">Cuaderno de campo</span>
                </span>
            </a>
            <div class="mobile-account" aria-label="Tu cuenta">
                <a class="mobile-account-profile" href="{{ route('profile.edit') }}">
                    @if (auth()->user()->hasMedia('profile-photo'))
                        <img class="topbar-avatar" src="{{ auth()->user()->getFirstMedia('profile-photo')?->getAvailableUrl(['optimized']) }}" alt="">
                    @else
                        <span class="topbar-avatar topbar-avatar-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    @endif
                    <span><strong>{{ auth()->user()->name }}</strong><small>Ver mi perfil</small></span>
                </a>
                <div class="mobile-account-controls">
                    <form method="POST" action="{{ route('locale.update') }}">
                        @csrf
                        <select name="locale" aria-label="{{ __('ui.language') }}" onchange="this.form.submit()" data-no-select2>
                            <option value="es" @selected(app()->getLocale() === 'es')>{{ __('ui.spanish') }}</option>
                            <option value="en" @selected(app()->getLocale() === 'en')>{{ __('ui.english') }}</option>
                        </select>
                    </form>
                    <span class="theme-choices" role="group" aria-label="{{ __('ui.theme') }}">
                        <button type="button" data-theme-choice="light" aria-label="{{ __('ui.light') }}">☀</button>
                        <button type="button" data-theme-choice="dark" aria-label="{{ __('ui.dark') }}">☾</button>
                    </span>
                    <form method="POST" action="{{ route('logout') }}" data-logout-form>
                        @csrf
                        <button class="mobile-account-logout" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </div>
            <nav class="main-nav" aria-label="Navegación principal">
                <div class="nav-group">
                <div class="nav-label">Operación</div>
                @can('dashboard.view')
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg> {{ __('ui.menu.dashboard') }}
                </a>
                @endcan
                @can('animals.view')
                <a class="nav-link {{ request()->routeIs('animals.*') ? 'active' : '' }}" href="{{ route('animals.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 8 4 5 2 7l2 5v4a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-4l2-5-2-2-3 3z"/><path d="M9 12h.01M15 12h.01M9 16h6"/></svg> {{ __('ui.menu.animals') }}
                </a>
                @endcan
                </div>
                <div class="nav-group">
                <div class="nav-label">Fincas y movimientos</div>
                @can('animals.view')
                <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}">🔔 {{ __('ui.menu.notifications') }}</a>
                @endcan
                @can('animals.view')
                <a class="nav-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}" href="{{ route('calendar.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg> {{ __('ui.menu.calendar') }}
                </a>
                @endcan
                @can('farms.view')
                <a class="nav-link {{ request()->routeIs('farms.*') ? 'active' : '' }}" href="{{ route('farms.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 21V9l9-6 9 6v12"/><path d="M7 21v-8h10v8M10 13v3m4-3v3M2 21h20"/></svg> {{ __('ui.menu.farms') }}
                </a>
                @endcan
                @can('paddocks.view')
                <a class="nav-link {{ request()->routeIs('paddocks.*') ? 'active' : '' }}" href="{{ route('paddocks.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18m6-18v18"/></svg> {{ __('ui.menu.paddocks') }}
                </a>
                @endcan
                @can('animal-movements.view')
                <a class="nav-link {{ request()->routeIs('animal-movements.*') ? 'active' : '' }}" href="{{ route('animal-movements.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 7h14m0 0-4-4m4 4-4 4M17 17H3m0 0 4 4m-4-4 4-4"/></svg> {{ __('ui.menu.movements') }}
                </a>
                @endcan
                </div>
                <div class="nav-group">
                <div class="nav-label">Salud y producción</div>
                @can('reproduction.view')
                <a class="nav-link {{ request()->routeIs('reproduction.*') ? 'active' : '' }}" href="{{ route('reproduction.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg> {{ __('ui.menu.reproduction') }}
                </a>
                @endcan
                @can('milk-production.view')
                <a class="nav-link {{ request()->routeIs('milk-production.*', 'animals.production.*') ? 'active' : '' }}" href="{{ route('milk-production.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.5S5 10 5 15a7 7 0 0 0 14 0c0-5-7-12.5-7-12.5Z"/><path d="M9 16a3 3 0 0 0 3 3"/></svg> {{ __('ui.menu.milk') }}
                </a>
                @endcan
                @can('weights.view')
                <a class="nav-link {{ request()->routeIs('weights.*') ? 'active' : '' }}" href="{{ route('weights.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 17 9 11l4 4 8-9"/><path d="M15 6h6v6M4 21h16"/></svg> {{ __('ui.menu.weights') }}
                </a>
                @endcan
                @can('finance.view')
                <a class="nav-link {{ request()->routeIs('finance.*') ? 'active' : '' }}" href="{{ route('finance.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><ellipse cx="12" cy="12" rx="9" ry="9"/><path d="M15.5 8.5c-.7-.7-1.8-1-3.1-1-1.7 0-2.9.8-2.9 2s1 1.8 2.8 2.2l.8.2c1.8.4 2.9 1 2.9 2.3s-1.3 2.3-3.3 2.3c-1.4 0-2.7-.5-3.5-1.3M12 5.5v13"/></svg> {{ __('ui.menu.finance') }}
                </a>
                @endcan
                </div>
                <div class="nav-group">
                <div class="nav-label">Finanzas y reportes</div>
                @can('reports.view')
                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3h10l4 4v14H5z"/><path d="M15 3v5h5M8 13h8m-8 4h8"/></svg> Reportes
                </a>
                @endcan
                </div>
                <div class="nav-group nav-group-admin">
                <div class="nav-label">Administración</div>
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
                </div>
                <div class="nav-group nav-group-system">
                <div class="nav-label">Aplicación</div>
                <button class="nav-link" type="button" data-pwa-install>
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3v12m-5-5 5 5 5-5"/><path d="M4 17v4h16v-4"/></svg> Instalar aplicación
                </button>
                </div>
            </nav>
            <div class="sidebar-foot">Registro de finca<br>Control claro, cada dia.</div>
        </aside>
        <div class="mobile-menu-backdrop" data-mobile-menu-close hidden></div>

        @php
            $isPrimaryMobileRoute = request()->routeIs('dashboard', 'animals.*', 'milk-production.*');
        @endphp
        <nav class="mobile-tabbar" aria-label="Navegación rápida">
            @can('dashboard.view')
            <a class="mobile-tab {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
                <span>Inicio</span>
            </a>
            @endcan
            @can('animals.view')
            <a class="mobile-tab {{ request()->routeIs('animals.*') && ! request()->routeIs('animals.production.*') ? 'active' : '' }}" href="{{ route('animals.index') }}" @if (request()->routeIs('animals.*') && ! request()->routeIs('animals.production.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 8 4 5 2 7l2 5v4a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-4l2-5-2-2-3 3z"/><path d="M9 12h.01M15 12h.01M9 16h6"/></svg>
                <span>Animales</span>
            </a>
            @endcan
            @can('milk-production.view')
            <a class="mobile-tab {{ request()->routeIs('milk-production.*', 'animals.production.*') ? 'active' : '' }}" href="{{ route('milk-production.index') }}" @if (request()->routeIs('milk-production.*', 'animals.production.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.5S5 10 5 15a7 7 0 0 0 14 0c0-5-7-12.5-7-12.5Z"/><path d="M9 16a3 3 0 0 0 3 3"/></svg>
                <span>Leche</span>
            </a>
            @endcan
            <button type="button" class="mobile-tab {{ $isPrimaryMobileRoute ? '' : 'active' }}" data-mobile-menu-toggle aria-controls="app-sidebar" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                <span>Más</span>
            </button>
        </nav>

        <div class="workspace">
            <header class="topbar">
                <span class="topbar-title">Administración de finca <span aria-hidden="true">/</span> Ganadería</span>
                <div class="topbar-account">
                    <div class="connection-indicator-wrap">
                        <button type="button" class="connection-indicator" data-offline-indicator data-state="checking" aria-haspopup="true" aria-expanded="false" aria-controls="offline-panel">
                            <span class="connection-dot" aria-hidden="true"></span>
                            <span class="connection-label" data-offline-indicator-label>Comprobando…</span>
                            <span class="connection-count" data-offline-indicator-count hidden>0</span>
                        </button>
                        <div class="offline-panel" id="offline-panel" data-offline-panel role="dialog" aria-label="Registros guardados sin conexión" hidden>
                            <div class="offline-panel-head">
                                <strong>Modo sin conexión</strong>
                                <p data-offline-panel-state>Verificando la conexión con el servidor.</p>
                            </div>
                            <ul class="offline-queue" data-offline-list></ul>
                            <div class="offline-panel-foot">
                                <button type="button" class="button button-small" data-offline-sync-now disabled>Sincronizar ahora</button>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('locale.update') }}" class="language-control">
                        @csrf
                        <label class="theme-control" for="locale-preference">
                            <span class="visually-hidden">{{ __('ui.language') }}</span>
                            <select id="locale-preference" name="locale" aria-label="{{ __('ui.language') }}" onchange="this.form.submit()">
                                <option value="es" @selected(app()->getLocale() === 'es')>{{ __('ui.spanish') }}</option>
                                <option value="en" @selected(app()->getLocale() === 'en')>{{ __('ui.english') }}</option>
                            </select>
                        </label>
                    </form>
                    <label class="theme-control" for="theme-preference">
                        <span class="visually-hidden">{{ __('ui.theme') }}</span>
                        <span class="theme-choices" role="group" aria-label="{{ __('ui.theme') }}">
                            <button type="button" data-theme-choice="light" aria-label="{{ __('ui.light') }}">☀</button>
                            <button type="button" data-theme-choice="dark" aria-label="{{ __('ui.dark') }}">☾</button>
                        </span>
                    </label>
                    <a class="topbar-profile" href="{{ route('profile.edit') }}" aria-label="Editar perfil de {{ auth()->user()->name }}">
                        @if (auth()->user()->hasMedia('profile-photo'))
                            <img class="topbar-avatar" src="{{ auth()->user()->getFirstMedia('profile-photo')?->getAvailableUrl(['optimized']) }}" alt="">
                        @else
                            <span class="topbar-avatar topbar-avatar-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                        <span class="topbar-status"><span class="status-dot"></span> {{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" data-logout-form>
                        @csrf
                        <button class="button button-secondary button-small" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </header>
            <main class="main-content">
                <div class="offline-status" data-offline-status role="status" aria-live="polite" hidden></div>
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
    <x-assistant />
</body>
</html>
