@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Cuenta personal</div>
            <h1>Mi perfil</h1>
            <p class="page-subtitle">Actualiza tu información y la foto que aparece en tu espacio de trabajo.</p>
        </div>
    </div>

    <form class="form-layout profile-layout" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <section class="form-panel">
            <div class="section-heading">
                <h2>Información personal</h2>
                <p>Estos datos identifican tu cuenta en Rincón Ganadero.</p>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="name">Nombre completo *</label>
                    <input class="control" id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="255" required>
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="email">Correo electrónico *</label>
                    <input class="control" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" maxlength="255" required>
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <x-image-upload
                    name="photo"
                    label="Foto de perfil"
                    :current-url="$user->getFirstMedia('profile-photo')?->getAvailableUrl(['optimized'])"
                    :alt="'Foto de perfil de '.$user->name"
                    remove-name="remove_photo"
                    remove-label="Eliminar la foto de perfil actual"
                />
            </div>
            <div class="form-actions">
                <button class="button button-primary" type="submit">Guardar cambios</button>
            </div>
        </section>
        <aside class="side-panel profile-account-card">
            <span class="profile-account-avatar">
                @if ($user->hasMedia('profile-photo'))
                    <img src="{{ $user->getFirstMedia('profile-photo')?->getAvailableUrl(['optimized']) }}" alt="">
                @else
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                @endif
            </span>
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <span class="tag tag-active">Cuenta activa</span>
        </aside>
    </form>

    <section class="form-panel profile-password-panel">
        <div class="section-heading">
            <h2>Cambiar contraseña</h2>
            <p>Usa al menos 12 caracteres, con mayúsculas, minúsculas y números.</p>
        </div>
        <form class="profile-password-form" method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')
            <div class="field-grid">
                <div class="field">
                    <label for="current_password">Contraseña actual *</label>
                    <input class="control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    @error('current_password')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="password">Nueva contraseña *</label>
                    <input class="control" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                    @error('password')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirmar nueva contraseña *</label>
                    <input class="control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                </div>
            </div>
            <div class="form-actions">
                <button class="button button-primary" type="submit">Actualizar contraseña</button>
            </div>
        </form>
    </section>
@endsection
