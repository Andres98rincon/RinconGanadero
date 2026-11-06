@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    <div class="page-head admin-page-head">
        <div>
            <div class="eyebrow">Control de acceso</div>
            <h1>Roles</h1>
            <p class="page-subtitle">Agrupa permisos para administrar qué puede consultar y operar cada equipo.</p>
        </div>
        @can('create', Spatie\Permission\Models\Role::class)
            <a class="button button-primary" href="{{ route('admin.roles.create') }}"><span aria-hidden="true">+</span> Crear rol</a>
        @endcan
    </div>

    <section class="table-wrap" aria-label="Roles y permisos">
        <table class="data-table">
            <thead><tr><th>Rol</th><th>Permisos</th><th>Usuarios asignados</th><th>Acciones</th></tr></thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td><strong>{{ str($role->name)->replace('-', ' ')->title() }}</strong>
                            @if (in_array($role->name, $systemRoles, true))
                                <span class="tag system-role-tag">Sistema</span>
                            @endif
                        </td>
                        <td>{{ number_format($role->permissions_count) }}</td>
                        <td>{{ number_format($role->users->count()) }}</td>
                        <td>
                            @if (! in_array($role->name, $systemRoles, true))
                                <div class="admin-actions">
                                    @can('update', $role)
                                        <a class="button button-secondary button-small" href="{{ route('admin.roles.edit', $role) }}">Editar permisos</a>
                                    @endcan
                                    @can('delete', $role)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="¿Eliminar el rol {{ $role->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger button-small" type="submit">Eliminar</button>
                                        </form>
                                    @endcan
                                </div>
                            @else
                                <span class="animal-sub">Rol protegido</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    <div class="role-notice permission-note">Los roles base del sistema están protegidos. El superadministrador siempre conserva acceso completo mediante Gate.</div>
@endsection
