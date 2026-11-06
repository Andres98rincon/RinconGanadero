@extends('layouts.app')

@section('title', 'Registrar pesaje')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('weights.index') }}">Pesajes y crecimiento</a> / Nuevo registro</div>
            <h1>Registrar pesaje</h1>
            <p class="page-subtitle">Ingresa el peso medido para actualizar el historial y el peso actual del animal.</p>
        </div>
    </div>

    <section class="form-panel">
        <form class="filter-bar" method="GET" action="{{ route('weights.create') }}">
            <div class="field filter-search">
                <label for="q">Buscar animal</label>
                <input class="control" id="q" type="search" name="q" value="{{ $search }}" placeholder="Arete o nombre" maxlength="100">
            </div>
            <button class="button button-secondary" type="submit">Buscar</button>
        </form>

        @if ($animals->isEmpty())
            <div class="empty-state">
                <strong>{{ $search || $selectedAnimalId ? 'No encontramos animales activos.' : 'Busca un animal para comenzar.' }}</strong>
                {{ $search || $selectedAnimalId ? 'Prueba con otro arete o nombre.' : 'Escribe al menos parte del arete o del nombre para cargar coincidencias.' }}
            </div>
        @else
            <p class="page-subtitle">Se muestran hasta 50 animales activos. Afina la búsqueda si no encuentras el arete o nombre.</p>
            <form method="POST" action="{{ route('weights.store') }}">
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
                        <label for="event_date">Fecha del pesaje *</label>
                        <input class="control" id="event_date" name="event_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('event_date', today()->toDateString()) }}" required>
                        @error('event_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="weight_kg">Peso (kg) *</label>
                        <input class="control" id="weight_kg" name="weight_kg" type="number" step="0.01" min="0.01" max="99999.99" value="{{ old('weight_kg') }}" required>
                        @error('weight_kg')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="description">Observaciones</label>
                        <textarea class="control" id="description" name="description" maxlength="10000">{{ old('description') }}</textarea>
                        @error('description')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ route('weights.index') }}">Cancelar</a>
                    <button class="button button-primary" type="submit">Guardar pesaje</button>
                </div>
            </form>
        @endif
    </section>
@endsection
