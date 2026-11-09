@extends('layouts.app')

@php($isEditing = $species !== null)

@section('title', $isEditing ? 'Editar especie' : 'Nueva especie')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.species.index') }}">Especies</a> / {{ $isEditing ? 'Editar' : 'Nuevo registro' }}</div>
            <h1>{{ $isEditing ? 'Editar especie' : 'Nueva especie' }}</h1>
            <p class="page-subtitle">El nombre es el que verán los usuarios al registrar animales.</p>
        </div>
    </div>

    <section class="form-panel">
        <form method="POST" action="{{ $isEditing ? route('admin.species.update', $species) : route('admin.species.store') }}">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif
            <div class="field-grid">
                <div class="field">
                    <label for="name">Nombre de la especie *</label>
                    <input class="control" id="name" name="name" value="{{ old('name', $species?->name) }}" maxlength="80" placeholder="Ej.: Bovino" required>
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                @if ($isEditing)
                    <div class="field">
                        <label for="code">Código interno</label>
                        <input class="control" id="code" value="{{ $species->code }}" disabled>
                        <span class="animal-sub">No cambia porque los animales registrados lo usan.</span>
                    </div>
                @endif
                <div class="field field-full">
                    <label for="description">Descripción</label>
                    <textarea class="control" id="description" name="description" maxlength="1000">{{ old('description', $species?->description) }}</textarea>
                    @error('description')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('admin.species.index') }}">Cancelar</a>
                <button class="button button-primary" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Registrar especie' }}</button>
            </div>
        </form>
    </section>
@endsection
