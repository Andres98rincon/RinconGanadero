@extends('layouts.app')

@section('title', 'Editar producción · ' . $animal->ear_tag)

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('animals.production.index', $animal) }}">Producción</a> / Editar registro</div>
            <h1>Editar producción diaria</h1>
            <p class="page-subtitle">{{ $animal->name ?: $animal->ear_tag }} · {{ $productionRecord->record_date->format('d/m/Y') }}</p>
        </div>
    </div>

    <div class="form-layout">
        <section class="form-panel">
            <form method="POST" action="{{ route('animals.production.update', [$animal, $productionRecord]) }}">
                @csrf
                @method('PUT')
                <div class="field-grid">
                    <div class="field">
                        <label for="record_date">Fecha *</label>
                        <input class="control" id="record_date" name="record_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('record_date', $productionRecord->record_date->toDateString()) }}" required>
                        @error('record_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="morning_liters">Ordeño de la mañana (L)</label>
                        <input class="control" id="morning_liters" name="morning_liters" type="number" step="0.01" min="0" value="{{ old('morning_liters', $productionRecord->morning_liters) }}">
                        @error('morning_liters')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="evening_liters">Ordeño de la tarde (L)</label>
                        <input class="control" id="evening_liters" name="evening_liters" type="number" step="0.01" min="0" value="{{ old('evening_liters', $productionRecord->evening_liters) }}">
                        @error('evening_liters')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="notes">Observaciones</label>
                        <textarea class="control" id="notes" name="notes" maxlength="5000">{{ old('notes', $productionRecord->notes) }}</textarea>
                        @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ route('animals.production.index', $animal) }}">Cancelar</a>
                    <button class="button button-primary" type="submit">Guardar cambios</button>
                </div>
            </form>
        </section>
    </div>
@endsection
