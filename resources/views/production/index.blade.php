@extends('layouts.app')

@section('title', 'Producción de leche')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Manejo del hato</div>
            <h1>Producción de leche</h1>
            <p class="page-subtitle">Consolida los ordeños diarios y consulta el rendimiento por finca y por vaca.</p>
        </div>
        <a class="button button-primary" href="{{ route('milk-production.create') }}"><span aria-hidden="true">+</span> Registrar producción</a>
    </div>

    <section class="stats-grid" aria-label="Resumen de producción de leche">
        <div class="stat"><span class="stat-label">Leche del período</span><strong class="stat-value">{{ number_format((float) $totals->liters, 2, ',', '.') }}<span class="stat-note">L</span></strong></div>
        <div class="stat"><span class="stat-label">Promedio por registro</span><strong class="stat-value">{{ number_format($averagePerCowDay, 2, ',', '.') }}<span class="stat-note">L</span></strong></div>
        <div class="stat"><span class="stat-label">Vacas con registro</span><strong class="stat-value">{{ number_format((int) $totals->cow_count) }}</strong></div>
        <div class="stat"><span class="stat-label">Días-vaca registrados</span><strong class="stat-value">{{ number_format((int) $totals->record_count) }}</strong></div>
    </section>

    <form class="filter-bar" method="GET" action="{{ route('milk-production.index') }}">
        <label class="field">
            <span>Desde</span>
            <input class="control" type="date" name="from" value="{{ $filters['from'] }}" max="{{ today()->toDateString() }}" required>
        </label>
        <label class="field">
            <span>Hasta</span>
            <input class="control" type="date" name="to" value="{{ $filters['to'] }}" max="{{ today()->toDateString() }}" required>
        </label>
        <select class="filter-control" name="farm_id" aria-label="Filtrar por finca">
            <option value="">Todas las fincas</option>
            @foreach ($farms as $farm)
                <option value="{{ $farm->id }}" @selected((string) ($filters['farm_id'] ?? '') === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="animal_id" aria-label="Filtrar por vaca">
            <option value="">Todas las vacas</option>
            @foreach ($cows as $cow)
                <option value="{{ $cow->id }}" @selected((string) ($filters['animal_id'] ?? '') === (string) $cow->id)>{{ $cow->ear_tag }}{{ $cow->name ? ' · ' . $cow->name : '' }}</option>
            @endforeach
        </select>
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por arete o nombre" aria-label="Buscar vaca">
        <button class="button button-secondary" type="submit">Filtrar</button>
        <a class="button button-secondary" href="{{ route('milk-production.index') }}">Limpiar</a>
    </form>

    <section class="table-wrap" aria-label="Producción total por día">
        <div class="farm-animals-heading">
            <div>
                <h2>Producción diaria</h2>
                <p>Litros totales y vacas registradas por fecha.</p>
            </div>
        </div>
        @if ($dailyTotals->isEmpty())
            <div class="empty-state"><strong>Sin producción en este período.</strong>Ajusta las fechas o registra los ordeños del hato.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Vacas registradas</th><th>Total del día</th></tr></thead>
                <tbody>
                    @foreach ($dailyTotals as $day)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($day->record_date)->format('d/m/Y') }}</td>
                            <td>{{ number_format((int) $day->cow_count) }}</td>
                            <td><strong>{{ number_format((float) $day->total_liters, 2, ',', '.') }} L</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $dailyTotals->firstItem() }}–{{ $dailyTotals->lastItem() }} de {{ $dailyTotals->total() }} días</span>
                {{ $dailyTotals->onEachSide(1)->links() }}
            </div>
        @endif
    </section>

    <div class="section-heading farm-animals-heading">
        <div>
            <h2>Rendimiento por vaca</h2>
            <p>Las diez vacas con mayor volumen en el período seleccionado.</p>
        </div>
    </div>
    <section class="table-wrap" aria-label="Rendimiento de leche por vaca">
        @if ($topCows->isEmpty())
            <div class="empty-state"><strong>Sin vacas para mostrar.</strong>Los registros aparecerán aquí al capturar producción.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Vaca</th><th>Finca</th><th>Días registrados</th><th>Promedio diario</th><th>Total del período</th><th></th></tr></thead>
                <tbody>
                    @foreach ($topCows as $summary)
                        <tr>
                            <td><a class="animal-link" href="{{ route('animals.production.index', $summary->animal) }}">{{ $summary->animal->ear_tag }}</a><span class="animal-sub">{{ $summary->animal->name ?: 'Sin nombre' }}</span></td>
                            <td>{{ $summary->animal->farm?->name ?? 'Sin finca asignada' }}</td>
                            <td>{{ number_format((int) $summary->days_recorded) }}</td>
                            <td>{{ number_format((float) $summary->average_liters, 2, ',', '.') }} L</td>
                            <td><strong>{{ number_format((float) $summary->period_liters, 2, ',', '.') }} L</strong></td>
                            <td><a class="button button-secondary button-small" href="{{ route('milk-production.create', ['animal_id' => $summary->animal_id]) }}">Registrar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <div class="section-heading farm-animals-heading">
        <div>
            <h2>Registros diarios</h2>
            <p>Historial del período filtrado, con enlace a la ficha productiva de cada vaca.</p>
        </div>
    </div>
    <section class="table-wrap" aria-label="Registros diarios de leche">
        @if ($records->isEmpty())
            <div class="empty-state"><strong>No hay registros para estos filtros.</strong>Registra la producción diaria para consultar el detalle aquí.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Vaca</th><th>Finca</th><th>Mañana</th><th>Tarde</th><th>Total</th><th>Observaciones</th></tr></thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td>{{ $record->record_date->format('d/m/Y') }}</td>
                            <td><a class="animal-link" href="{{ route('animals.production.index', $record->animal) }}">{{ $record->animal->ear_tag }}</a><span class="animal-sub">{{ $record->animal->name ?: 'Sin nombre' }}</span></td>
                            <td>{{ $record->animal->farm?->name ?? 'Sin finca asignada' }}</td>
                            <td>{{ $record->morning_liters !== null ? number_format((float) $record->morning_liters, 2, ',', '.') . ' L' : '—' }}</td>
                            <td>{{ $record->evening_liters !== null ? number_format((float) $record->evening_liters, 2, ',', '.') . ' L' : '—' }}</td>
                            <td><strong>{{ number_format((float) $record->total_liters, 2, ',', '.') }} L</strong></td>
                            <td>{{ \Illuminate\Support\Str::limit($record->notes, 50) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $records->firstItem() }}–{{ $records->lastItem() }} de {{ $records->total() }}</span>
                {{ $records->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
