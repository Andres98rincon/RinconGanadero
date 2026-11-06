@extends('layouts.app')

@section('title', 'Fincas')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administración ganadera</div>
            <h1>Fincas</h1>
            <p class="page-subtitle">Ubicaciones, capacidad y animales asociados a cada explotación.</p>
        </div>
        <a class="button button-primary" href="{{ route('farms.create') }}"><span aria-hidden="true">+</span> Registrar finca</a>
    </div>

    <section class="stats-grid" aria-label="Resumen de fincas">
        <div class="stat"><span class="stat-label">Fincas registradas</span><strong class="stat-value">{{ number_format($stats['total']) }}</strong></div>
        <div class="stat"><span class="stat-label">Fincas activas</span><strong class="stat-value">{{ number_format($stats['active']) }}</strong></div>
        <div class="stat"><span class="stat-label">Animales asignados</span><strong class="stat-value">{{ number_format($stats['animals']) }}</strong></div>
        <div class="stat"><span class="stat-label">Extensión activa</span><strong class="stat-value">{{ number_format((float) $stats['area'], 2, ',', '.') }}<span class="stat-note">ha</span></strong></div>
    </section>

    <form class="filter-bar" method="GET" action="{{ route('farms.index') }}">
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por finca, código, municipio o encargado" aria-label="Buscar fincas">
        <select class="filter-control" name="status" aria-label="Filtrar por estado">
            <option value="">Todos los estados</option>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="button button-secondary" type="submit">Filtrar</button>
        @if ($filters)
            <a class="button button-secondary" href="{{ route('farms.index') }}">Limpiar</a>
        @endif
    </form>

    <section class="table-wrap" aria-label="Fincas registradas">
        @if ($farms->isEmpty())
            <div class="empty-state">
                <strong>{{ $filters ? 'No encontramos fincas con esos filtros.' : 'Todavía no hay fincas registradas.' }}</strong>
                {{ $filters ? 'Prueba con otros datos de búsqueda.' : 'Registra una finca para comenzar a organizar tus animales por ubicación.' }}
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr><th>Código / finca</th><th>Ubicación</th><th>Encargado</th><th>Extensión</th><th>Animales</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    @foreach ($farms as $farm)
                        <tr>
                            <td><a class="animal-link" href="{{ route('farms.show', $farm) }}">{{ $farm->code }}</a><span class="animal-sub">{{ $farm->name }}</span></td>
                            <td>{{ $farm->municipality ?: 'Sin municipio' }}<span class="animal-sub">{{ $farm->province ?: 'Sin departamento' }}</span></td>
                            <td>{{ $farm->manager_name ?: 'Sin asignar' }}</td>
                            <td>{{ $farm->area_hectares ? number_format((float) $farm->area_hectares, 2, ',', '.') . ' ha' : '—' }}</td>
                            <td>{{ number_format($farm->animals_count) }}</td>
                            <td><span class="tag {{ $farm->status === 'active' ? 'tag-active' : 'tag-muted' }}">{{ $statusOptions[$farm->status] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $farms->firstItem() }}–{{ $farms->lastItem() }} de {{ $farms->total() }}</span>
                {{ $farms->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
