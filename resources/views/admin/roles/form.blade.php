@extends('layouts.app')

@php($isEditing = $role !== null)

@section('title', $isEditing ? 'Editar rol' : 'Crear rol')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.roles.index') }}">Roles</a> / {{ $isEditing ? 'Editar permisos' : 'Nuevo rol' }}</div>
            <h1>{{ $isEditing ? 'Editar rol' : 'Crear rol' }}</h1>
            <p class="page-subtitle">Selecciona los permisos que tendrá este grupo de usuarios.</p>
        </div>
    </div>

    <form class="form-layout" method="POST" action="{{ $isEditing ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif
        <section class="form-panel admin-form-panel">
            <div class="field">
                <label for="name">Identificador del rol *</label>
                <input class="control" id="name" name="name" value="{{ old('name', $role?->name) }}" placeholder="ej. supervisor_campo" maxlength="100" required>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="section-heading permission-section-heading">
                <h2>Permisos disponibles</h2>
                <p>El superadministrador no necesita asignación manual; siempre tiene acceso total.</p>
            </div>
            <div class="permission-group-grid">
                @foreach ($permissionGroups as $group => $permissions)
                    <section class="permission-group">
                        <h3>{{ $group }}</h3>
                        @foreach ($permissions as $permission => $label)
                            @php($permissionId = $availablePermissions->get($permission)?->id)
                            @if ($permissionId)
                                <label class="permission-check">
                                    <input type="checkbox" name="permissions[]" value="{{ $permissionId }}" @checked(in_array($permissionId, old('permissions', $permissionIds)))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endif
                        @endforeach
                    </section>
                @endforeach
            </div>
            @error('permissions.*')<span class="field-error">{{ $message }}</span>@enderror
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('admin.roles.index') }}">Cancelar</a>
                <button class="button button-primary" type="submit">{{ $isEditing ? 'Guardar rol' : 'Crear rol' }}</button>
            </div>
        </section>
        <aside class="side-panel">
            <h3>Diseña accesos claros</h3>
            <p>Asigna solo los permisos necesarios para cada función. Puedes actualizar el rol y sus usuarios heredarán los cambios al instante.</p>
            <ul>
                <li>Los permisos de consulta no habilitan cambios.</li>
                <li>Las cuentas de administrador y operador son roles base protegidos.</li>
                <li>No se puede eliminar un rol con usuarios asignados.</li>
            </ul>
        </aside>
    </form>
@endsection
