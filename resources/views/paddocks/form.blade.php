@extends('layouts.app')

@php($isEditing = isset($paddock))

@section('title', $isEditing ? 'Editar potrero' : 'Registrar potrero')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('paddocks.index') }}">Potreros</a> / {{ $isEditing ? 'Editar' : 'Nuevo registro' }}</div>
            <h1>{{ $isEditing ? 'Editar potrero' : 'Registrar potrero' }}</h1>
            <p class="page-subtitle">Cada potrero pertenece a una finca y puede tener área y capacidad registradas.</p>
        </div>
    </div>

    <section class="form-panel">
        <form method="POST" action="{{ $isEditing ? route('paddocks.update', $paddock) : route('paddocks.store') }}">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif
            <div class="field-grid">
                <div class="field">
                    <label for="farm_id">Finca *</label>
                    <select class="control" id="farm_id" name="farm_id" required>
                        <option value="">Selecciona la finca</option>
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" @selected((string) old('farm_id', $paddock->farm_id ?? $selectedFarmId ?? '') === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
                        @endforeach
                    </select>
                    @error('farm_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="code">Código del potrero *</label>
                    <input class="control" id="code" name="code" value="{{ old('code', $paddock->code ?? '') }}" maxlength="40" required>
                    @error('code')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="name">Nombre *</label>
                    <input class="control" id="name" name="name" value="{{ old('name', $paddock->name ?? '') }}" maxlength="120" required>
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="area_hectares">Área (hectáreas)</label>
                    <input class="control" id="area_hectares" name="area_hectares" type="number" step="0.01" min="0" value="{{ old('area_hectares', $paddock->area_hectares ?? '') }}">
                    @error('area_hectares')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="capacity">Capacidad de animales</label>
                    <input class="control" id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $paddock->capacity ?? '') }}">
                    @error('capacity')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="status">Estado *</label>
                    <select class="control" id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $paddock->status ?? 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field field-full">
                    <label for="notes">Notas</label>
                    <textarea class="control" id="notes" name="notes" maxlength="10000">{{ old('notes', $paddock->notes ?? '') }}</textarea>
                    @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('paddocks.index', ['farm_id' => $selectedFarmId]) }}">Cancelar</a>
                <button class="button button-primary" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Registrar potrero' }}</button>
            </div>
        </form>
    </section>
@endsection
