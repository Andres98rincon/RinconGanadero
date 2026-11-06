<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#183d32">
    <meta name="application-name" content="{{ config('app.name') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48" type="image/x-icon">
    <link rel="icon" href="{{ asset('images/rincon-ganadero.png') }}" type="image/png">
    <title>Error {{ $status }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="error-page">
    <main class="error-card">
        <span class="error-code">{{ $status }}</span>
        <h1>No pudimos completar la operación</h1>
        <p>{{ $message }}</p>
        <div class="error-actions">
            <button class="button button-secondary" type="button" data-history-back>Volver</button>
            <a class="button button-primary" href="{{ route('login') }}">Ir al inicio</a>
        </div>
    </main>
    <div class="visually-hidden" data-swal-error="{{ $message }}" role="alert"></div>
</body>
</html>
