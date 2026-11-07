<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <title>@yield('title') | {{ config('app.name') }}</title>
    <style>
        [data-app-loading] { position: fixed; inset: 0; z-index: 1000; display: grid; place-items: center; background: #17392f; color: #f5f8f1; }
        [data-app-loading] .app-loading-card { display: grid; justify-items: center; gap: 10px; text-align: center; font-family: system-ui, sans-serif; }
        [data-app-loading] .app-loading-spinner { width: 28px; height: 28px; border: 3px solid rgb(255 255 255 / 28%); border-top-color: #f2b178; border-radius: 50%; animation: app-loading-spin .8s linear infinite; }
        @keyframes app-loading-spin { to { transform: rotate(360deg); } }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    {{-- <div class="app-loading" data-app-loading role="status" aria-live="polite">
        <div class="app-loading-card">
            <img src="{{ asset('images/rincon-ganadero.png') }}" alt="" class="app-loading-logo">
            <div class="app-loading-spinner" aria-hidden="true"></div>
            <strong>Bienvenido a Rincón Ganadero</strong>
            <span>Cargando tu espacio ganadero…</span>
        </div>
    </div> --}}
    @if (session('status'))
        <div class="visually-hidden" data-swal-toast="{{ session('status') }}" role="status"></div>
    @endif
    @if (session('error'))
        <div class="visually-hidden" data-swal-error="{{ session('error') }}" role="alert"></div>
    @endif
    <main class="auth-shell">
        <section class="auth-visual" aria-label="Gestión ganadera Rincón Ganadero">
            <img src="{{ asset('images/girolando-5-8.jpg') }}" alt="">
            <div class="auth-visual-overlay"></div>
            <a class="auth-brand" href="{{ route('login') }}">
                <img class="brand-mark" src="{{ asset('images/rincon-ganadero.png') }}" alt="Logo de Rincón Ganadero con ganado Gyr y las iniciales AR">
                <span><strong>RINCÓN GANADERO</strong><small>Gestión ganadera</small></span>
            </a>
            <div class="auth-visual-copy">
                <span class="auth-kicker"><span></span> CONTROL · CUIDADO · CRECIMIENTO</span>
                <h1>Tu finca crece<br>cuando todo está<br>en buenas manos.</h1>
                <p>Un espacio seguro para cuidar el hato y tomar mejores decisiones cada día.</p>
            </div>
            <span class="auth-photo-credit">Ganado Girolando · Wikimedia Commons</span>
        </section>
        <section class="auth-content">
            <div class="auth-card">
                @yield('content')
            </div>
            <p class="auth-footer">Rincón Ganadero <span>·</span> Administración segura de finca</p>
        </section>
    </main>
</body>
</html>
