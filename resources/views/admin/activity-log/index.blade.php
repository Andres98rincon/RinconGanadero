@extends('layouts.app')

@section('title', 'Bitácora de auditoría')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administración del sistema</div>
            <h1>Bitácora de auditoría</h1>
            <p class="page-subtitle">Trazabilidad de cambios y operaciones realizadas en la plataforma.</p>
        </div>
    </div>

    <form class="filter-bar" method="GET" action="{{ route('admin.activity-log.index') }}">
        <select class="filter-control" name="user_id" data-searchable-select data-placeholder="Todos los usuarios">
            <option value="">Todos los usuarios</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="module" data-searchable-select data-placeholder="Todos los módulos">
            <option value="">Todos los módulos</option>
            @foreach (['animals' => 'Animales', 'animal-movements' => 'Movimientos', 'farms' => 'Fincas', 'paddocks' => 'Potreros', 'reproduction' => 'Reproducción', 'milk-production' => 'Producción de leche', 'finance' => 'Finanzas', 'users' => 'Usuarios', 'authentication' => 'Autenticación', 'backups' => 'Respaldos'] as $module => $label)
                <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="control" type="text" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Acción (created, updated…)" aria-label="Filtrar por acción">
        <input class="control" type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Desde">
        <input class="control" type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Hasta">
        <button class="button button-secondary" type="submit">Filtrar</button>
        @if ($filters)
            <a class="button button-secondary" href="{{ route('admin.activity-log.index') }}">Limpiar</a>
        @endif
    </form>

    <section class="table-wrap" aria-label="Actividades registradas">
        @if ($activities->isEmpty())
            <div class="empty-state"><strong>No hay actividades para mostrar.</strong>Prueba con otros filtros.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Módulo / acción</th><th>Registro</th><th>Cambios</th></tr></thead>
                <tbody>
                    @foreach ($activities as $activity)
                        <tr>
                            <td>{{ $activity->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $activity->causer?->name ?? 'Sistema' }}</td>
                            <td><span class="tag">{{ $activity->log_name ?: 'sistema' }}</span><small class="animal-sub">{{ $activity->event ?: $activity->description }}</small></td>
                            <td>{{ $activity->subject?->name ?? $activity->subject?->ear_tag ?? $activity->subject?->code ?? ($activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—') }}</td>
                            <td>
                                @if ($activity->properties->isNotEmpty())
                                    <details>
                                        <summary>Ver cambios</summary>
                                        <pre class="audit-properties">{{ json_encode($activity->properties->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $activities->firstItem() }}–{{ $activities->lastItem() }} de {{ $activities->total() }}</span>
                {{ $activities->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
