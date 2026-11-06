@extends('layouts.app')

@section('title', 'Registrar producción de leche')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('milk-production.index') }}">Producción de leche</a> / Nuevo registro</div>
            <h1>Registrar producción diaria</h1>
            <p class="page-subtitle">Captura los litros de la mañana y/o la tarde para una vaca del hato.</p>
        </div>
    </div>

    <div class="form-layout">
        <section class="form-panel">
            @if ($cows->isEmpty())
                <div class="notice notice-error" role="status">
                    No hay vacas bovinas activas disponibles para registrar producción.
                    <a href="{{ route('animals.create') }}">Registrar un animal</a>
                </div>
            @endif
            <form method="POST" action="{{ route('milk-production.store') }}">
                @csrf
                <div class="field-grid">
                    <div class="field field-full">
                        <label for="animal_id">Vaca *</label>
                        <select class="control" id="animal_id" name="animal_id" required>
                            <option value="">Selecciona una vaca</option>
                            @foreach ($cows as $cow)
                                <option value="{{ $cow->id }}" @selected((string) old('animal_id', $selectedCowId) === (string) $cow->id)>
                                    {{ $cow->ear_tag }}{{ $cow->name ? ' · ' . $cow->name : '' }}{{ $cow->farm ? ' · ' . $cow->farm->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('animal_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="record_date">Fecha *</label>
                        <input class="control" id="record_date" name="record_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('record_date', today()->toDateString()) }}" required>
                        @error('record_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="morning_liters">Ordeño de la mañana (L)</label>
                        <input class="control" id="morning_liters" name="morning_liters" type="number" step="0.01" min="0" max="99999.99" value="{{ old('morning_liters') }}">
                        @error('morning_liters')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="evening_liters">Ordeño de la tarde (L)</label>
                        <input class="control" id="evening_liters" name="evening_liters" type="number" step="0.01" min="0" max="99999.99" value="{{ old('evening_liters') }}">
                        @error('evening_liters')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="notes">Observaciones</label>
                        <textarea class="control" id="notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>
                        @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ route('milk-production.index') }}">Cancelar</a>
                    <button class="button button-primary" type="submit" @disabled($cows->isEmpty())>Guardar producción</button>
                </div>
            </form>
        </section>
    </div>
@endsection
