@extends('layouts.app')

@section('title', 'Pesajes y crecimiento')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Manejo del hato</div>
            <h1>Pesajes y crecimiento</h1>
            <p class="page-subtitle">Controla la evolución del peso y la ganancia diaria de cada animal.</p>
        </div>
        <div class="page-actions">
            <a class="button button-secondary" href="{{ route('weights.estimate') }}">Calcular peso con cinta</a>
            <a class="button button-primary" href="{{ route('weights.create') }}"><span aria-hidden="true">+</span> Registrar pesaje</a>
        </div>
    </div>

    <section class="stats-grid" aria-label="Resumen de pesajes">
        <div class="stat"><span class="stat-label">Pesajes del período</span><strong class="stat-value">{{ number_format((int) $stats->record_count) }}</strong></div>
        <div class="stat"><span class="stat-label">Animales pesados</span><strong class="stat-value">{{ number_format((int) $stats->animal_count) }}</strong></div>
        <div class="stat"><span class="stat-label">Peso promedio registrado</span><strong class="stat-value">{{ number_format((float) $stats->average_weight, 2, ',', '.') }}<span class="stat-note">kg</span></strong></div>
        <div class="stat"><span class="stat-label">Mayor peso registrado</span><strong class="stat-value">{{ number_format((float) $stats->maximum_weight, 2, ',', '.') }}<span class="stat-note">kg</span></strong></div>
    </section>

    <form class="filter-bar" method="GET" action="{{ route('weights.index') }}">
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
        <select class="filter-control" name="species" aria-label="Filtrar por especie">
            <option value="">Todas las especies</option>
            @foreach ($speciesOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['species'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar arete o nombre" aria-label="Buscar animal">
        @if (! empty($filters['animal_id']))
            <input type="hidden" name="animal_id" value="{{ $filters['animal_id'] }}">
        @endif
        <button class="button button-secondary" type="submit">Filtrar</button>
        <a class="button button-secondary" href="{{ route('weights.index') }}">Limpiar</a>
    </form>

    <div class="section-heading farm-animals-heading">
        <div>
            <h2>Historial de pesajes</h2>
            <p>La variación se compara con el pesaje anterior; si no existe, usa el peso al nacer registrado.</p>
        </div>
    </div>
    <section class="table-wrap" aria-label="Historial de peso y crecimiento">
        @if ($records->isEmpty())
            <div class="empty-state">
                <strong>Sin pesajes para los filtros seleccionados.</strong>
                Registra un pesaje o amplía el período para consultar el crecimiento.
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr><th>Fecha</th><th>Animal</th><th>Finca</th><th>Especie</th><th>Peso previo</th><th>Peso medido</th><th>Variación</th><th>Ganancia diaria</th><th>Observaciones</th></tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td>{{ $record->event_date->format('d/m/Y') }}</td>
                            <td><a class="animal-link" href="{{ route('animals.show', $record->animal) }}">{{ $record->animal->ear_tag }}</a><span class="animal-sub">{{ $record->animal->name ?: 'Sin nombre' }}</span></td>
                            <td>{{ $record->animal->farm?->name ?? 'Sin finca asignada' }}</td>
                            <td>{{ $speciesOptions[$record->animal->species] ?? 'Otro' }}</td>
                            <td>
                                @if ($record->previous_weight_kg !== null)
                                    {{ number_format((float) $record->previous_weight_kg, 2, ',', '.') }} kg
                                    <span class="animal-sub">{{ $record->previous_event_date ? \Illuminate\Support\Carbon::parse($record->previous_event_date)->format('d/m/Y') : 'Peso al nacer' }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td><strong>{{ number_format((float) $record->weight_kg, 2, ',', '.') }} kg</strong></td>
                            <td>
                                @if ($record->weight_change_kg !== null)
                                    <span class="tag {{ $record->weight_change_kg >= 0 ? 'tag-active' : 'tag-warning' }}">{{ $record->weight_change_kg > 0 ? '+' : '' }}{{ number_format((float) $record->weight_change_kg, 2, ',', '.') }} kg</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $record->average_daily_gain !== null ? number_format((float) $record->average_daily_gain, 3, ',', '.') . ' kg/día' : '—' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($record->description, 55) ?: '—' }}</td>
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
