@extends('layouts.app')

@php($isEditing = isset($farm) && $farm !== null)

@section('title', $isEditing ? 'Editar finca' : 'Registrar finca')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('farms.index') }}">Fincas</a> / {{ $isEditing ? 'Editar' : 'Nuevo registro' }}</div>
            <h1>{{ $isEditing ? 'Editar finca' : 'Registrar finca' }}</h1>
            <p class="page-subtitle">Registra la ubicación y los datos operativos para organizar los animales.</p>
        </div>
    </div>

    <form method="POST" action="{{ $isEditing ? route('farms.update', $farm) : route('farms.store') }}">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif
        <div class="form-layout">
            <div class="form-panel">
                <div class="section-heading">
                    <h2>Identificación y ubicación</h2>
                    <p>El código distingue la finca; la ubicación y extensión ayudan a planificar el manejo.</p>
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label for="code">Código de finca *</label>
                        <input class="control" id="code" name="code" value="{{ old('code', $farm->code ?? '') }}" maxlength="30" required>
                        @error('code')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="name">Nombre de la finca *</label>
                        <input class="control" id="name" name="name" value="{{ old('name', $farm->name ?? '') }}" maxlength="150" required>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="address">Dirección o referencia</label>
                        <input class="control" id="address" name="address" value="{{ old('address', $farm->address ?? '') }}" maxlength="255">
                        @error('address')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="municipality">Municipio</label>
                        <input class="control" id="municipality" name="municipality" value="{{ old('municipality', $farm->municipality ?? '') }}" maxlength="100">
                        @error('municipality')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="province">Departamento / provincia</label>
                        <input class="control" id="province" name="province" value="{{ old('province', $farm->province ?? '') }}" maxlength="100">
                        @error('province')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="area_hectares">Extensión (hectáreas)</label>
                        <input class="control" id="area_hectares" name="area_hectares" type="number" step="0.01" min="0" value="{{ old('area_hectares', $farm->area_hectares ?? '') }}">
                        @error('area_hectares')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="section-heading">
                    <h2>Contacto y operación</h2>
                    <p>Datos de referencia para el equipo responsable de la finca.</p>
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label for="manager_name">Encargado</label>
                        <input class="control" id="manager_name" name="manager_name" value="{{ old('manager_name', $farm->manager_name ?? '') }}" maxlength="150">
                        @error('manager_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="status">Estado *</label>
                        <select class="control" id="status" name="status" required>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $farm->status ?? 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="phone">Teléfono</label>
                        <input class="control" id="phone" name="phone" value="{{ old('phone', $farm->phone ?? '') }}" maxlength="40">
                        @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="email">Correo electrónico</label>
                        <input class="control" id="email" name="email" type="email" value="{{ old('email', $farm->email ?? '') }}" maxlength="150">
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="notes">Notas</label>
                        <textarea class="control" id="notes" name="notes" maxlength="10000">{{ old('notes', $farm->notes ?? '') }}</textarea>
                        @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ $isEditing ? route('farms.show', $farm) : route('farms.index') }}">Cancelar</a>
                    <button class="button button-primary" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Guardar finca' }}</button>
                </div>
            </div>
            <aside class="side-panel">
                <h3>Fincas y animales</h3>
                <p>Después de guardar, podrás asignar esta finca al registrar o editar cada animal y revisar su inventario desde el detalle.</p>
                <ul>
                    <li>El código debe ser único.</li>
                    <li>Los animales conservan su propio potrero o corral.</li>
                    <li>Una finca con animales asociados no se puede archivar.</li>
                </ul>
            </aside>
        </div>
    </form>
@endsection
