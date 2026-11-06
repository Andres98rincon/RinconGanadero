@extends('layouts.app')

@section('title', 'Valorar inventario animal')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('finance.index') }}">Finanzas y costos</a> / Valoración del hato</div>
            <h1>Valorar animal</h1>
            <p class="page-subtitle">Registra una estimación con fecha; la valoración más reciente representa su valor actual.</p>
        </div>
    </div>

    <section class="form-panel">
        <form class="filter-bar" method="GET" action="{{ route('finance.valuations.create') }}">
            <div class="field filter-search">
                <label for="q">Buscar animal activo</label>
                <input class="control" id="q" type="search" name="q" value="{{ $search }}" placeholder="Arete o nombre" maxlength="100">
            </div>
            <button class="button button-secondary" type="submit">Buscar</button>
        </form>

        @if ($animals->isEmpty())
            <div class="empty-state">
                <strong>{{ $search || $selectedAnimalId ? 'No encontramos animales activos.' : 'Busca un animal para valorar.' }}</strong>
                {{ $search || $selectedAnimalId ? 'Prueba con otro arete o nombre.' : 'Las valoraciones se registran individualmente para conservar el historial.' }}
            </div>
        @else
            <p class="page-subtitle">Se muestran hasta 50 animales activos. La valoración no modifica ingresos ni flujo de caja.</p>
            <form method="POST" action="{{ route('finance.valuations.store') }}">
                @csrf
                <div class="field-grid">
                    <div class="field field-full">
                        <label for="animal_id">Animal *</label>
                        <select class="control" id="animal_id" name="animal_id" required>
                            <option value="">Selecciona un animal</option>
                            @foreach ($animals as $animal)
                                <option value="{{ $animal->id }}" @selected((string) old('animal_id', $selectedAnimalId) === (string) $animal->id)>
                                    {{ $animal->ear_tag }}{{ $animal->name ? ' · ' . $animal->name : '' }}{{ $animal->farm ? ' · ' . $animal->farm->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('animal_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="valuation_date">Fecha de valoración *</label>
                        <input class="control" id="valuation_date" name="valuation_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('valuation_date', today()->toDateString()) }}" required>
                        @error('valuation_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="amount">Valor estimado *</label>
                        <input class="control" id="amount" name="amount" type="number" step="0.01" min="0" max="9999999999.99" value="{{ old('amount') }}" required>
                        @error('amount')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="notes">Criterio / observaciones</label>
                        <textarea class="control" id="notes" name="notes" maxlength="10000">{{ old('notes') }}</textarea>
                        @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ route('finance.index') }}">Cancelar</a>
                    <button class="button button-primary" type="submit">Guardar valoración</button>
                </div>
            </form>
        @endif
    </section>
@endsection
