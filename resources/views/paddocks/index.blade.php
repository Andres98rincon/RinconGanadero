@extends('layouts.app')

@section('title', 'Potreros y corrales')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Inventario de ubicaciones</div>
            <h1>Potreros y corrales</h1>
            <p class="page-subtitle">Organiza la capacidad y ubicación actual de los animales dentro de cada finca.</p>
        </div>
        <div class="detail-actions">
            <a class="button button-primary" href="{{ route('paddocks.create', ['farm_id' => $filters['farm_id'] ?? '']) }}">Agregar potrero</a>
        </div>
    </div>

    <section class="stats-grid" aria-label="Resumen de potreros">
        <div class="stat"><span class="stat-label">Potreros registrados</span><strong class="stat-value">{{ number_format($stats['paddocks']) }}</strong></div>
        <div class="stat"><span class="stat-label">Potreros activos</span><strong class="stat-value">{{ number_format($stats['active']) }}</strong></div>
        <div class="stat"><span class="stat-label">Animales con potrero asignado</span><strong class="stat-value">{{ number_format($stats['animals']) }}</strong></div>
    </section>

    <form class="filter-bar" method="GET" action="{{ route('paddocks.index') }}">
        <select class="filter-control" name="farm_id" aria-label="Filtrar por finca">
            <option value="">Todas las fincas</option>
            @foreach ($farms as $farm)
                <option value="{{ $farm->id }}" @selected((string) ($filters['farm_id'] ?? '') === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="status" aria-label="Filtrar por estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Paddock::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar código, potrero o finca" aria-label="Buscar potrero">
        <button class="button button-secondary" type="submit">Filtrar</button>
        <a class="button button-secondary" href="{{ route('paddocks.index') }}">Limpiar</a>
    </form>

    <section class="table-wrap" aria-label="Listado de potreros">
        @if ($paddocks->isEmpty())
            <div class="empty-state"><strong>No hay potreros con esos filtros.</strong>Agrega un potrero para organizar la ubicación del ganado.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Potrero</th><th>Finca</th><th>Área</th><th>Capacidad</th><th>Animales</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach ($paddocks as $paddock)
                        <tr>
                            <td><strong>{{ $paddock->name }}</strong><span class="animal-sub">{{ $paddock->code }}</span></td>
                            <td>{{ $paddock->farm?->code }} · {{ $paddock->farm?->name }}</td>
                            <td>@if ($paddock->area_hectares !== null){{ number_format((float) $paddock->area_hectares, 2, ',', '.') }} ha<span class="animal-sub">{{ number_format($paddock->area_square_meters, 0, ',', '.') }} m²</span>@else—@endif</td>
                            <td>{{ $paddock->capacity ? number_format($paddock->capacity) : 'Sin límite' }}</td>
                            <td>{{ number_format($paddock->animals_count) }}</td>
                            <td><span class="tag {{ $paddock->status === 'active' ? 'tag-active' : 'tag-muted' }}">{{ \App\Models\Paddock::STATUSES[$paddock->status] }}</span></td>
                            <td>
                                <div class="detail-actions">
                                    <a class="button button-secondary button-small" href="{{ route('paddocks.edit', $paddock) }}">Editar</a>
                                    <form method="POST" action="{{ route('paddocks.destroy', $paddock) }}" data-confirm="Archivar este potrero? Solo se pueden archivar potreros sin animales asignados.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button button-danger button-small" type="submit">Archivar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $paddocks->firstItem() }}–{{ $paddocks->lastItem() }} de {{ $paddocks->total() }} potreros</span>
                {{ $paddocks->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
