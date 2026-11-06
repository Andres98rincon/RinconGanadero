@extends('layouts.app')

@section('title', 'Permisos')

@section('content')
    <div class="page-head admin-page-head">
        <div>
            <div class="eyebrow">Control de acceso</div>
            <h1>Permisos</h1>
            <p class="page-subtitle">Consulta las capacidades del sistema y crea permisos para futuras funciones.</p>
        </div>
        @can('create', Spatie\Permission\Models\Permission::class)
            <a class="button button-primary" href="{{ route('admin.permissions.create') }}"><span aria-hidden="true">+</span> Crear permiso</a>
        @endcan
    </div>

    <section class="table-wrap" aria-label="Permisos disponibles">
        <table class="data-table">
            <thead><tr><th>Permiso</th><th>Descripción</th><th>Roles asignados</th><th>Acción</th></tr></thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr>
                        <td><strong>{{ $permission->name }}</strong></td>
                        <td>{{ $labels[$permission->name] ?? str($permission->name)->replace(['.', '-', '_'], ' ')->ucfirst() }}</td>
                        <td>{{ number_format($permission->roles_count) }}</td>
                        <td>
                            @can('delete', $permission)
                                <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" data-confirm="¿Eliminar el permiso {{ $permission->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-danger button-small" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
