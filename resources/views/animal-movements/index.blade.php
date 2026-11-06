@extends('layouts.app')

@section('title', 'Compras, ventas y traslados')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Inventario y comercialización</div>
            <h1>Compras, ventas y traslados</h1>
            <p class="page-subtitle">Historial de movimientos, precios y ubicación de cada animal. Compras y ventas también se registran en Finanzas.</p>
        </div>
        <div class="detail-actions">
            <a class="button button-primary" href="{{ route('animal-movements.create') }}"><span aria-hidden="true">+</span> Registrar movimiento</a>
        </div>
    </div>

    <section class="stats-grid" aria-label="Resumen de movimientos">
        <div class="stat"><span class="stat-label">Animales comprados</span><strong class="stat-value">{{ number_format($totals->purchases) }}</strong><span class="stat-note">${{ number_format((float) $totals->purchase_amount, 2, ',', '.') }} en compras</span></div>
        <div class="stat"><span class="stat-label">Animales vendidos</span><strong class="stat-value">{{ number_format($totals->sales) }}</strong><span class="stat-note">${{ number_format((float) $totals->sale_amount, 2, ',', '.') }} en ventas</span></div>
        <div class="stat"><span class="stat-label">Traslados internos</span><strong class="stat-value">{{ number_format($totals->transfers) }}</strong></div>
        <div class="stat"><span class="stat-label">Resultado comercial</span><strong class="stat-value">${{ number_format((float) $totals->sale_amount - (float) $totals->purchase_amount, 2, ',', '.') }}</strong><span class="stat-note">Ventas menos compras</span></div>
    </section>

    <form class="filter-bar" method="GET" action="{{ route('animal-movements.index') }}">
        <div class="field">
            <label for="from">Desde</label>
            <input class="control" id="from" type="date" name="from" value="{{ $filters['from'] }}" max="{{ today()->toDateString() }}" required>
        </div>
        <div class="field">
            <label for="to">Hasta</label>
            <input class="control" id="to" type="date" name="to" value="{{ $filters['to'] }}" max="{{ today()->toDateString() }}" required>
        </div>
        <select class="filter-control" name="farm_id" aria-label="Filtrar por finca">
            <option value="">Todas las fincas</option>
            @foreach ($farms as $farm)
                <option value="{{ $farm->id }}" @selected((string) ($filters['farm_id'] ?? '') === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="movement_type" aria-label="Filtrar por tipo">
            <option value="">Todos los movimientos</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(($filters['movement_type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar arete, proveedor o referencia" aria-label="Buscar movimiento">
        <button class="button button-secondary" type="submit">Filtrar</button>
        <a class="button button-secondary" href="{{ route('animal-movements.index') }}">Limpiar</a>
    </form>

    <section class="table-wrap" aria-label="Historial de movimientos">
        @if ($movements->isEmpty())
            <div class="empty-state"><strong>No hay movimientos con esos filtros.</strong>Registra compras, ventas o traslados para consultar el historial del ganado.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Animal</th><th>Origen</th><th>Destino</th><th>Precio</th><th>Contraparte / motivo de salida</th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr>
                            <td>{{ $movement->movement_date->format('d/m/Y') }}</td>
                            <td><span class="tag {{ in_array($movement->movement_type, ['purchase', 'sale'], true) ? 'tag-warning' : 'tag-active' }}">{{ $types[$movement->movement_type] }}</span></td>
                            <td>
                                @if ($movement->animal)
                                    <a class="animal-link" href="{{ route('animals.show', $movement->animal) }}">{{ $movement->animal_ear_tag }}</a>
                                @else
                                    <strong>{{ $movement->animal_ear_tag }}</strong>
                                @endif
                                @if ($movement->animal?->name)<span class="animal-sub">{{ $movement->animal->name }}</span>@endif
                            </td>
                            <td>
                                @if ($movement->fromFarm || $movement->from_farm_name)
                                    {{ $movement->fromFarm?->name ?? $movement->from_farm_name }}{{ $movement->from_location ? ' · ' . $movement->from_location : '' }}
                                @else
                                    {{ $movement->movement_type === 'purchase' ? 'Proveedor / origen' : '—' }}
                                @endif
                            </td>
                            <td>
                                @if ($movement->toFarm || $movement->to_farm_name)
                                    {{ $movement->toFarm?->name ?? $movement->to_farm_name }}{{ $movement->to_location ? ' · ' . $movement->to_location : '' }}
                                @else
                                    {{ $movement->movement_type === 'sale' ? 'Comprador / destino' : '—' }}
                                @endif
                            </td>
                            <td>{{ $movement->amount !== null ? '$' . number_format((float) $movement->amount, 2, ',', '.') : '—' }}</td>
                            <td>
                                {{ $movement->counterparty_name ?: '—' }}
                                @if ($movement->exit_reason)
                                    <span class="animal-sub">Motivo: {{ \App\Models\AnimalMovement::EXIT_REASONS[$movement->exit_reason] ?? $movement->exit_reason }}</span>
                                @endif
                                @if ($movement->reference)<span class="animal-sub">Ref. {{ $movement->reference }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $movements->firstItem() }}–{{ $movements->lastItem() }} de {{ $movements->total() }} movimientos</span>
                {{ $movements->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
