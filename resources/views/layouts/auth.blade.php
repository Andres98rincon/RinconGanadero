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
    <title>@yield('title') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
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
