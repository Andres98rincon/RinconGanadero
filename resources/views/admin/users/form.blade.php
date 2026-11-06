@extends('layouts.app')

@php($isEditing = $managedUser !== null)

@section('title', $isEditing ? 'Editar usuario' : 'Crear usuario')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.users.index') }}">Usuarios</a> / {{ $isEditing ? 'Editar acceso' : 'Nueva cuenta' }}</div>
            <h1>{{ $isEditing ? 'Editar usuario' : 'Crear usuario' }}</h1>
            <p class="page-subtitle">Los accesos dependen del rol asignado y pueden actualizarse en cualquier momento.</p>
        </div>
    </div>

    <form class="form-layout" method="POST" action="{{ $isEditing ? route('admin.users.update', $managedUser) : route('admin.users.store') }}" @unless($isEditing) data-user-creation @endunless data-password-set="{{ filled($managedUser?->password) ? 'true' : 'false' }}" data-google-linked="{{ filled($managedUser?->google_id) ? 'true' : 'false' }}">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif
        <section class="form-panel admin-form-panel">
            <div class="section-heading">
                <h2>Datos de la cuenta</h2>
                <p>La contraseña debe tener mínimo 12 caracteres, mayúsculas, minúsculas y números. Para Google es opcional.</p>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="name">Nombre completo *</label>
                    <input class="control" id="name" name="name" value="{{ old('name', $managedUser?->name) }}" autocomplete="name" maxlength="255" required>
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="email">Correo electrónico *</label>
                    <input class="control" id="email" name="email" type="email" value="{{ old('email', $managedUser?->email) }}" autocomplete="email" maxlength="255" required>
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                @if (! $isEditing || ! auth()->user()->is($managedUser))
                    <div class="field">
                        <label for="auth_method">Método de acceso *</label>
                        <select class="control" id="auth_method" name="auth_method" data-auth-method-select required>
                            <option value="password" @selected(old('auth_method', $managedUser?->auth_method ?? 'password') === 'password')>Correo y contraseña</option>
                            <option value="google" @selected(old('auth_method', $managedUser?->auth_method) === 'google')>Google (prerregistro)</option>
                        </select>
                        @error('auth_method')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="account_status">Estado de la cuenta *</label>
                        <select class="control" id="account_status" name="account_status" data-account-status-select required>
                            @foreach (['pending' => 'Pendiente', 'active' => 'Activa', 'inactive' => 'Inactiva'] as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected(old('account_status', $managedUser?->account_status ?? (old('auth_method') === 'google' ? 'pending' : 'active')) === $statusValue)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        @error('account_status')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                @endif
                <div class="field">
                    <label for="password">Contraseña {{ $isEditing ? '(dejar en blanco para conservarla)' : '' }}</label>
                    <input class="control" id="password" name="password" type="password" autocomplete="new-password" @required(! $isEditing && old('auth_method', 'password') === 'password')>
                    @error('password')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <input class="control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" {{ $isEditing ? '' : 'required' }}>
                </div>
            </div>

            <div class="section-heading">
                <h2>Rol y permisos</h2>
                <p>Un rol agrupa los permisos que la persona necesita para su trabajo.</p>
            </div>
            @if ($isEditing && auth()->user()->is($managedUser))
                <div class="role-notice">Por seguridad, no puedes cambiar tu propio rol desde esta pantalla. Solicita el cambio a otro superadministrador.</div>
            @else
                <div class="role-grid">
                    @foreach ($roles as $role)
                        <label class="role-option">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $managedUser?->roles->modelKeys() ?? [])))>
                            <span>
                                <strong>{{ str($role->name)->replace('-', ' ')->title() }}</strong>
                                <small>
                                    @if ($role->name === App\AccessControl::ADMIN_ROLE)
                                        Administra los módulos ganaderos y las cuentas del equipo.
                                    @elseif ($role->name === App\AccessControl::OPERATOR_ROLE)
                                        Acceso operativo a los módulos asignados, sin administración de usuarios.
                                    @else
                                        Rol personalizado; sus permisos se definen en el módulo de roles.
                                    @endif
                                </small>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('roles')<span class="field-error">{{ $message }}</span>@enderror
                @error('roles.*')<span class="field-error">{{ $message }}</span>@enderror
            @endif

            @if (! $isEditing || ! auth()->user()->is($managedUser))
                <div class="section-heading">
                    <h2>Fincas asignadas</h2>
                    <p>Este usuario solo podrá consultar y gestionar información de las fincas seleccionadas.</p>
                </div>
                <div class="field">
                    <label for="farm_ids">Fincas *</label>
                    <select class="control" id="farm_ids" name="farm_ids[]" multiple data-select2 data-placeholder="Seleccionar fincas" required>
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" @selected(in_array((string) $farm->id, old('farm_ids', array_map('strval', $managedUser?->farms->modelKeys() ?? [])), true))>
                                {{ $farm->name }} ({{ $farm->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('farm_ids')<span class="field-error">{{ $message }}</span>@enderror
                    @error('farm_ids.*')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            @endif

            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('admin.users.index') }}">Cancelar</a>
                <button class="button button-primary" type="submit">{{ $isEditing ? 'Guardar usuario' : 'Crear cuenta' }}</button>
            </div>
        </section>
        <aside class="side-panel">
            <h3>Acceso protegido</h3>
            <p>No existe registro público. Las cuentas solo pueden crearse desde esta sección por personal autorizado.</p>
            <ul>
                <li>El usuario recibirá sus credenciales de forma privada.</li>
                <li>Puede recuperar su contraseña desde la pantalla de inicio.</li>
                <li>El superadministrador se crea únicamente por consola.</li>
            </ul>
        </aside>
    </form>
@endsection
