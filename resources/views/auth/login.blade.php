@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="auth-heading">
        <span class="auth-icon" aria-hidden="true">↗</span>
        <span class="eyebrow">Bienvenido de nuevo</span>
        <h2>Inicia sesión</h2>
        <p>Ingresa a tu espacio de gestión del hato.</p>
    </div>

    @if (session('status'))
        <div class="auth-status" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="auth-error" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="auth-error" data-validation-error role="alert">{{ $errors->first() }}</div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input class="control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <div class="auth-label-row">
                <label for="password">Contraseña</label>
                <a href="{{ route('password.request') }}">¿La olvidaste?</a>
            </div>
            <input class="control" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <label class="auth-remember"><input type="checkbox" name="remember" value="1"> <span>Mantener mi sesión iniciada</span></label>
        <button class="button button-primary auth-submit" type="submit">Entrar al panel <span aria-hidden="true">→</span></button>
    </form>

    @if (filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
        <div class="auth-divider"><span>o continúa con</span></div>
        <a class="button button-secondary auth-submit google-login-button" href="{{ route('google.redirect') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.75 3.27-8.1Z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.15v2.84A11 11 0 0 0 12 23Z"/><path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.15a11 11 0 0 0 0 9.9l3.69-2.84Z"/><path fill="#EA4335" d="M12 5.36c1.62 0 3.07.56 4.21 1.64l3.15-3.15A10.55 10.55 0 0 0 12 1 11 11 0 0 0 2.15 7.05l3.69 2.84C6.71 7.29 9.14 5.36 12 5.36Z"/></svg>
            Iniciar sesión con Google
        </a>
    @endif

    <div class="auth-secure-note"><span aria-hidden="true">◆</span> Acceso privado para el equipo autorizado de tu finca.</div>
    <button class="button button-secondary auth-submit" type="button" data-pwa-install>Instalar aplicación</button>
@endsection
