@extends('layouts.auth')

@section('title', 'Crear contraseña nueva')

@section('content')
    <div class="auth-heading">
        <span class="auth-icon" aria-hidden="true">✦</span>
        <span class="eyebrow">Último paso</span>
        <h2>Crea una contraseña nueva</h2>
        <p>Usa al menos 12 caracteres, con mayúsculas, minúsculas y números.</p>
    </div>

    @if ($errors->any())
        <div class="auth-error" data-validation-error role="alert">{{ $errors->first() }}</div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input class="control" id="email" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Contraseña nueva</label>
            <input class="control" id="password" name="password" type="password" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input class="control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button class="button button-primary auth-submit" type="submit">Guardar contraseña <span aria-hidden="true">→</span></button>
    </form>
    <a class="auth-back-link" href="{{ route('login') }}">← Volver a iniciar sesión</a>
@endsection
