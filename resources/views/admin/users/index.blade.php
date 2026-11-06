@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <div class="page-head admin-page-head">
        <div>
            <div class="eyebrow">Administración del sistema</div>
            <h1>Usuarios</h1>
            <p class="page-subtitle">Crea cuentas para tu equipo y asigna acceso según sus responsabilidades.</p>
        </div>
        @can('create', App\Models\User::class)
            <a class="button button-primary" href="{{ route('admin.users.create') }}"><span aria-hidden="true">+</span> Crear usuario</a>
        @endcan
    </div>

    <section class="table-wrap" aria-label="Usuarios del sistema">
        @if ($users->isEmpty())
            <div class="empty-state"><strong>Aún no hay usuarios de equipo.</strong>Crea una cuenta para que otra persona pueda iniciar sesión.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Usuario</th><th>Acceso</th><th>Roles asignados</th><th>Alta en el sistema</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <div class="admin-table-name">
                                    @if ($user->hasMedia('profile-photo'))
                                        <img class="admin-avatar admin-avatar-photo" src="{{ $user->getFirstMedia('profile-photo')?->getAvailableUrl(['optimized']) }}" alt="" loading="lazy">
                                    @else
                                        <span class="admin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    @endif
                                    <span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span>
                                </div>
                            </td>
                            <td>
                                <span class="tag {{ $user->account_status === 'active' ? 'tag-active' : 'tag-muted' }}">
                                    {{ ['pending' => 'Pendiente', 'active' => 'Activa', 'inactive' => 'Inactiva'][$user->account_status] ?? $user->account_status }}
                                </span>
                                <small class="animal-sub">{{ $user->auth_method === 'google' ? 'Google' : 'Contraseña' }}</small>
                            </td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <span class="tag {{ $role->name === App\AccessControl::ADMIN_ROLE ? 'tag-warning' : 'tag-active' }}">{{ str($role->name)->replace('-', ' ')->title() }}</span>
                                @endforeach
                            </td>
                            <td>{{ $user->created_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                <div class="admin-actions">
                                    @can('update', $user)
                                        <a class="button button-secondary button-small" href="{{ route('admin.users.edit', $user) }}">Editar</a>
                                    @endcan
                                    @can('delete', $user)
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="¿Eliminar el acceso de {{ $user->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger button-small" type="submit">Eliminar</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $users->firstItem() }}–{{ $users->lastItem() }} de {{ $users->total() }} usuarios</span>
                {{ $users->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
