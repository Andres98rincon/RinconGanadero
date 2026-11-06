@extends('layouts.auth')

@section('title', 'Recuperar contraseña')

@section('content')
    <div class="auth-heading">
        <span class="auth-icon" aria-hidden="true">↻</span>
        <span class="eyebrow">Recuperación segura</span>
        <h2>Restablece tu acceso</h2>
        <p>Te enviaremos un enlace para crear una contraseña nueva.</p>
    </div>

    @if (session('status'))
        <div class="auth-status" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="auth-error" data-validation-error role="alert">{{ $errors->first() }}</div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input class="control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        </div>
        <button class="button button-primary auth-submit" type="submit">Enviar enlace de recuperación <span aria-hidden="true">→</span></button>
    </form>
    <a class="auth-back-link" href="{{ route('login') }}">← Volver a iniciar sesión</a>
@endsection
