@extends('layouts.app')

@php($isEditing = $breed !== null)

@section('title', $isEditing ? 'Editar raza' : 'Nueva raza')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.breeds.index') }}">Razas</a> / {{ $isEditing ? 'Editar' : 'Nuevo registro' }}</div>
            <h1>{{ $isEditing ? 'Editar raza' : 'Nueva raza' }}</h1>
            <p class="page-subtitle">La raza solo se mostrará al registrar animales de la especie seleccionada.</p>
        </div>
    </div>

    <section class="form-panel">
        <form method="POST" action="{{ $isEditing ? route('admin.breeds.update', $breed) : route('admin.breeds.store') }}">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif
            <div class="field-grid">
                <div class="field">
                    <label for="species_id">Especie *</label>
                    <select class="control" id="species_id" name="species_id" required>
                        <option value="">Selecciona la especie</option>
                        @foreach ($species as $item)
                            <option value="{{ $item->id }}" @selected((string) old('species_id', $selectedSpeciesId) === (string) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                    @error('species_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="name">Nombre de la raza *</label>
                    <input class="control" id="name" name="name" value="{{ old('name', $breed?->name) }}" maxlength="100" placeholder="Ej.: Brahman" required>
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="origin">Origen</label>
                    <input class="control" id="origin" name="origin" value="{{ old('origin', $breed?->origin) }}" maxlength="100" placeholder="Ej.: Estados Unidos">
                    @error('origin')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field field-full">
                    <label for="description">Descripción</label>
                    <textarea class="control" id="description" name="description" maxlength="1000">{{ old('description', $breed?->description) }}</textarea>
                    @error('description')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('admin.breeds.index', ['species_id' => $selectedSpeciesId]) }}">Cancelar</a>
                <button class="button button-primary" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Registrar raza' }}</button>
            </div>
        </form>
    </section>
@endsection
