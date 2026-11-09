@extends('layouts.app')

@php($isEditing = isset($animal))

@section('title', $isEditing ? 'Editar ficha' : 'Registrar animal')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('animals.index') }}">Inventario</a> / {{ $isEditing ? 'Ficha' : 'Nuevo registro' }}</div>
            <h1>{{ $isEditing ? 'Editar animal' : 'Registrar animal' }}</h1>
            <p class="page-subtitle">Los campos con * son necesarios para identificar correctamente al animal.</p>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ $isEditing ? route('animals.update', $animal) : route('animals.store') }}">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif
        <div class="form-layout">
            <div class="form-panel">
                <div class="section-heading">
                    <h2>Identificación y origen</h2>
                    <p>Identificadores unicos, especie y datos de nacimiento o ingreso.</p>
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label for="ear_tag">Número de arete / identificación *</label>
                        <input class="control" id="ear_tag" name="ear_tag" value="{{ old('ear_tag', $animal->ear_tag ?? '') }}" required maxlength="40">
                        @error('ear_tag')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="name">Nombre <span>Opcional</span></label>
                        <input class="control" id="name" name="name" value="{{ old('name', $animal->name ?? '') }}" maxlength="100">
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="species">Especie *</label>
                        <select class="control" id="species" name="species" data-species-select data-searchable-select data-placeholder="Seleccionar especie" required>
                            <option value="">Seleccionar especie</option>
                            @foreach ($speciesOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('species', $animal->species ?? 'bovine') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('species')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="breed_id">Raza</label>
                        <select class="control" id="breed_id" name="breed_id" data-species-options data-searchable-select data-placeholder="Buscar raza">
                            <option value="">Sin especificar</option>
                            @foreach ($breeds as $breed)
                                <option value="{{ $breed->id }}" data-species="{{ $breed->species }}" @selected((string) old('breed_id', $animal->breed_id ?? '') === (string) $breed->id)>{{ $breed->name }} · {{ $speciesOptions[$breed->species] ?? 'Otro' }}</option>
                            @endforeach
                        </select>
                        @error('breed_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="sex">Sexo *</label>
                        <select class="control" id="sex" name="sex" required>
                            <option value="">Seleccionar</option>
                            <option value="female" @selected(old('sex', $animal->sex ?? '') === 'female')>Hembra</option>
                            <option value="male" @selected(old('sex', $animal->sex ?? '') === 'male')>Macho</option>
                            <option value="unknown" @selected(old('sex', $animal->sex ?? '') === 'unknown')>Sin definir</option>
                        </select>
                        @error('sex')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="identification_method">Método de identificación</label>
                        <select class="control" id="identification_method" name="identification_method">
                            @foreach (['visual' => 'Arete visual', 'rfid' => 'Arete electrónico / RFID', 'tattoo' => 'Tatuaje', 'brand' => 'Hierro', 'other' => 'Otro'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('identification_method', $animal->identification_method ?? 'visual') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="microchip">Microchip <span>Opcional</span></label>
                        <input class="control" id="microchip" name="microchip" value="{{ old('microchip', $animal->microchip ?? '') }}" maxlength="64">
                        @error('microchip')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="registration_number">Registro genealógico</label>
                        <input class="control" id="registration_number" name="registration_number" value="{{ old('registration_number', $animal->registration_number ?? '') }}" maxlength="64">
                    </div>
                    <div class="field">
                        <label for="birth_date">Fecha de nacimiento</label>
                        <input class="control" id="birth_date" name="birth_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('birth_date', $animal?->birth_date?->format('Y-m-d') ?? '') }}">
                        @error('birth_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="birth_weight_kg">Peso al nacer (kg)</label>
                        <input class="control" id="birth_weight_kg" name="birth_weight_kg" type="number" step="0.01" min="0" value="{{ old('birth_weight_kg', $animal->birth_weight_kg ?? '') }}">
                        @error('birth_weight_kg')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="dam_id">Madre</label>
                        <select class="control" id="dam_id" name="dam_id" data-species-options data-searchable-select data-placeholder="Buscar madre">
                            <option value="">No registrada</option>
                            @foreach ($parentAnimals->where('sex', 'female') as $parent)
                                <option value="{{ $parent->id }}" data-species="{{ $parent->species }}" @selected((string) old('dam_id', $animal->dam_id ?? '') === (string) $parent->id)>{{ $parent->ear_tag }}{{ $parent->name ? ' · ' . $parent->name : '' }}</option>
                            @endforeach
                        </select>
                        @error('dam_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="sire_id">Padre</label>
                        <select class="control" id="sire_id" name="sire_id" data-species-options data-searchable-select data-placeholder="Buscar padre">
                            <option value="">No registrado</option>
                            @foreach ($parentAnimals->where('sex', 'male') as $parent)
                                <option value="{{ $parent->id }}" data-species="{{ $parent->species }}" @selected((string) old('sire_id', $animal->sire_id ?? '') === (string) $parent->id)>{{ $parent->ear_tag }}{{ $parent->name ? ' · ' . $parent->name : '' }}</option>
                            @endforeach
                        </select>
                        @error('sire_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="section-heading">
                    <h2>Estado y seguimiento</h2>
                    <p>Uso productivo, peso, procedencia e información relevante para el equipo.</p>
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label for="status">Estado en la finca *</label>
                        <select class="control" id="status" name="status" required>
                            @foreach (\App\Models\Animal::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $animal->status ?? 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="purpose">Propósito productivo</label>
                        <select class="control" id="purpose" name="purpose">
                            <option value="">Sin especificar</option>
                            @foreach (['dairy' => 'Leche', 'meat' => 'Carne', 'breeding' => 'Reproducción', 'dual' => 'Doble propósito', 'work' => 'Trabajo', 'other' => 'Otro'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('purpose', $animal->purpose ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="reproductive_status">Estado reproductivo</label>
                        <select class="control" id="reproductive_status" name="reproductive_status">
                            <option value="">Sin dato</option>
                            @foreach (['open' => 'Abierta', 'served' => 'Servida, pendiente de diagnóstico', 'pregnant' => 'Gestante', 'lactating' => 'Lactando', 'dry' => 'Seca', 'not_applicable' => 'No aplica'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('reproductive_status', $animal->reproductive_status ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="farm_id">Finca</label>
                        <select class="control" id="farm_id" name="farm_id" data-searchable-select data-placeholder="Buscar finca">
                            <option value="">Sin finca asignada</option>
                            @foreach ($farms as $farm)
                                <option value="{{ $farm->id }}" @selected((string) old('farm_id', $animal->farm_id ?? $selectedFarmId ?? '') === (string) $farm->id)>{{ $farm->name }} · {{ $farm->code }}{{ $farm->status === 'inactive' ? ' · Inactiva' : '' }}</option>
                            @endforeach
                        </select>
                        @error('farm_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="location">Potrero / corral</label>
                        <input class="control" id="location" name="location" value="{{ old('location', $animal->location ?? '') }}" maxlength="120" placeholder="Ej. Potrero norte">
                        @error('location')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="current_weight_kg">Peso actual (kg)</label>
                        <input class="control" id="current_weight_kg" name="current_weight_kg" type="number" step="0.01" min="0" value="{{ old('current_weight_kg', $animal->current_weight_kg ?? '') }}">
                        @error('current_weight_kg')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="last_weighed_at">Fecha del último pesaje</label>
                        <input class="control" id="last_weighed_at" name="last_weighed_at" type="date" max="{{ now()->toDateString() }}" value="{{ old('last_weighed_at', $animal?->last_weighed_at?->format('Y-m-d') ?? '') }}">
                        @error('last_weighed_at')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="color">Color / pelaje</label>
                        <input class="control" id="color" name="color" value="{{ old('color', $animal->color ?? '') }}" maxlength="80">
                    </div>
                    <div class="field field-full">
                        <label for="markings">Señales y marcas</label>
                        <textarea class="control" id="markings" name="markings" maxlength="5000">{{ old('markings', $animal->markings ?? '') }}</textarea>
                    </div>
                    <div class="field">
                        <label for="acquisition_type">Forma de ingreso *</label>
                        <select class="control" id="acquisition_type" name="acquisition_type" required>
                            @foreach (['born' => 'Nacido en la finca', 'purchased' => 'Comprado', 'donated' => 'Donado', 'transferred' => 'Trasladado', 'unknown' => 'Desconocido'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('acquisition_type', $animal->acquisition_type ?? 'born') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="acquisition_date">Fecha de ingreso</label>
                        <input class="control" id="acquisition_date" name="acquisition_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('acquisition_date', $animal?->acquisition_date?->format('Y-m-d') ?? '') }}">
                        @error('acquisition_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="acquisition_cost">Costo de compra</label>
                        <input class="control" id="acquisition_cost" name="acquisition_cost" type="number" step="0.01" min="0" value="{{ old('acquisition_cost', $animal->acquisition_cost ?? '') }}">
                        @error('acquisition_cost')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="supplier_name">Proveedor / procedencia</label>
                        <input class="control" id="supplier_name" name="supplier_name" value="{{ old('supplier_name', $animal->supplier_name ?? '') }}" maxlength="150">
                    </div>
                    <div class="field field-full">
                        <label for="health_notes">Alertas y antecedentes de salud</label>
                        <textarea class="control" id="health_notes" name="health_notes" maxlength="10000">{{ old('health_notes', $animal->health_notes ?? '') }}</textarea>
                        @error('health_notes')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="notes">Notas generales</label>
                        <textarea class="control" id="notes" name="notes" maxlength="10000">{{ old('notes', $animal->notes ?? '') }}</textarea>
                    </div>
                    <x-image-upload
                        name="photo"
                        label="Fotografía del animal"
                        :current-url="$animal?->getFirstMedia('photo')?->getAvailableUrl(['optimized'])"
                        :alt="'Foto de '.($animal?->ear_tag ?? 'el animal')"
                        :remove-name="$isEditing ? 'remove_photo' : null"
                        remove-label="Eliminar la fotografía actual"
                    />
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ $isEditing ? route('animals.show', $animal) : route('animals.index') }}">Cancelar</a>
                    <button class="button button-primary" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Guardar animal' }}</button>
                </div>
            </div>
            <aside class="side-panel">
                <h3>Una ficha que crece contigo</h3>
                <p>El número de identificación no se puede repetir. El peso, la salud y los eventos reproductivos también pueden agregarse como historial desde el detalle del animal.</p>
                <ul>
                    <li>La edad se calcula a partir del nacimiento.</li>
                    <li>Los progenitores deben ser de la misma especie.</li>
                    <li>Al retirar un animal, su ficha queda archivada.</li>
                </ul>
            </aside>
        </div>
    </form>
@endsection
