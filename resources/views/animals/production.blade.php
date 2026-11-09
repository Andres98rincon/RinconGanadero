@extends('layouts.app')

@section('title', 'Producción · ' . $animal->ear_tag)

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('animals.show', $animal) }}">Ficha {{ $animal->ear_tag }}</a> / Producción</div>
            <h1>Historial productivo</h1>
            <p class="page-subtitle">{{ $animal->name ?: $animal->ear_tag }} · Control diario de leche en litros.</p>
        </div>
        <a class="button button-secondary" href="{{ route('milk-production.index', ['animal_id' => $animal->id]) }}">Ver panel general</a>
    </div>

    <section class="stats-grid" aria-label="Resumen de producción">
        <div class="stat"><span class="stat-label">Producción este mes</span><strong class="stat-value">{{ number_format((float) $monthTotal, 2, ',', '.') }}<span class="stat-note">L</span></strong></div>
        <div class="stat"><span class="stat-label">Producción acumulada</span><strong class="stat-value">{{ number_format((float) $allTimeTotal, 2, ',', '.') }}<span class="stat-note">L</span></strong></div>
        <div class="stat"><span class="stat-label">Días registrados</span><strong class="stat-value">{{ number_format($records->total()) }}</strong></div>
        <div class="stat"><span class="stat-label">Estado reproductivo</span><strong class="stat-value production-status">{{ ['open' => 'Abierta', 'served' => 'Servida', 'pregnant' => 'Preñada', 'lactating' => 'Lactando', 'dry' => 'Seca', 'not_applicable' => 'No aplica'][$animal->reproductive_status] ?? 'Sin dato' }}</strong></div>
    </section>

    <div class="production-layout">
        <section class="form-panel">
            <div class="section-heading">
                <h2>Registrar producción diaria</h2>
                <p>Ingresa uno o ambos ordeños. El total se calcula automáticamente.</p>
            </div>
            <form method="POST" action="{{ route('animals.production.store', $animal) }}" class="production-form" data-offline-form="milk-production" data-offline-animal-id="{{ $animal->id }}" data-offline-animal-label="{{ $animal->ear_tag }}{{ $animal->name ? ' · '.$animal->name : '' }}">
                @csrf
                <div class="field">
                    <label for="record_date">Fecha *</label>
                    <input class="control" id="record_date" name="record_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('record_date', today()->toDateString()) }}" required>
                    @error('record_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="morning_liters">Ordeño de la mañana (L)</label>
                    <input class="control" id="morning_liters" name="morning_liters" type="number" step="0.01" min="0" value="{{ old('morning_liters') }}">
                    @error('morning_liters')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="evening_liters">Ordeño de la tarde (L)</label>
                    <input class="control" id="evening_liters" name="evening_liters" type="number" step="0.01" min="0" value="{{ old('evening_liters') }}">
                    @error('evening_liters')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field field-full">
                    <label for="notes">Observaciones</label>
                    <textarea class="control" id="notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>
                    @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <button class="button button-primary" type="submit">Guardar producción</button>
            </form>
        </section>
    </div>

    <div class="section-heading farm-animals-heading">
        <div>
            <h2>Registros de producción</h2>
            <p>Un registro por día para evitar duplicados.</p>
        </div>
    </div>
    <section class="table-wrap" aria-label="Historial diario de producción de leche">
        @if ($records->isEmpty())
            <div class="empty-state">
                <strong>Sin producción registrada.</strong>
                Agrega el primer control diario para comenzar el historial.
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr><th>Fecha</th><th>Mañana</th><th>Tarde</th><th>Total</th><th>Observaciones</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td>{{ $record->record_date->format('d/m/Y') }}</td>
                            <td>{{ $record->morning_liters !== null ? number_format((float) $record->morning_liters, 2, ',', '.') . ' L' : '—' }}</td>
                            <td>{{ $record->evening_liters !== null ? number_format((float) $record->evening_liters, 2, ',', '.') . ' L' : '—' }}</td>
                            <td><strong>{{ number_format((float) $record->total_liters, 2, ',', '.') }} L</strong></td>
                            <td>{{ \Illuminate\Support\Str::limit($record->notes, 60) ?: '—' }}</td>
                            <td><a class="button button-secondary button-small" href="{{ route('animals.production.edit', [$animal, $record]) }}">Editar</a></td>
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
