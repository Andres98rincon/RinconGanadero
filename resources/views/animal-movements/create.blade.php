@extends('layouts.app')

@section('title', 'Registrar compra, venta o traslado')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('animal-movements.index') }}">Movimientos</a> / Nuevo registro</div>
            <h1>Registrar movimiento</h1>
            <p class="page-subtitle">Compras y ventas actualizan el inventario y generan su registro financiero en una sola operación.</p>
        </div>
    </div>

    <section class="form-panel">
        <form class="filter-bar" method="GET" action="{{ route('animal-movements.create') }}" data-movement-for="sale farm_transfer paddock_transfer">
            <input type="hidden" name="movement_type" value="{{ old('movement_type', $movementType) }}">
            @if ($selectedFarmId)
                <input type="hidden" name="to_farm_id" value="{{ $selectedFarmId }}">
            @endif
            <div class="field filter-search">
                <label for="q">Buscar animal activo</label>
                <input class="control" id="q" type="search" name="q" value="{{ $search }}" placeholder="Número de arete o nombre" maxlength="100">
            </div>
            <button class="button button-secondary" type="submit">Buscar animal</button>
        </form>

        @if ($search || $selectedAnimalId)
            @if ($animals->isEmpty())
                <div class="empty-state"><strong>No encontramos animales activos.</strong>Prueba con otro arete o nombre.</div>
            @else
                <p class="page-subtitle">Selecciona el animal encontrado en el formulario. Se muestran hasta 50 coincidencias.</p>
            @endif
        @else
            <p class="page-subtitle" data-movement-for="sale farm_transfer paddock_transfer">Busca el animal antes de registrar una venta o traslado.</p>
        @endif

        <form method="POST" action="{{ route('animal-movements.store') }}" data-movement-form data-paddock-options-template="{{ route('farms.paddock-options', ['farm' => 'FARM_ID']) }}">
            @csrf
            <div class="field-grid">
                <div class="field">
                    <label for="movement_type">Tipo de movimiento *</label>
                    <select class="control" id="movement_type" name="movement_type" data-movement-type required>
                        <option value="">Selecciona el tipo</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('movement_type', $movementType) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('movement_type')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="movement_date">Fecha *</label>
                    <input class="control" id="movement_date" name="movement_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('movement_date', today()->toDateString()) }}" required>
                    @error('movement_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field-grid" data-movement-for="sale farm_transfer paddock_transfer">
                <div class="field field-full">
                    <label for="animal_id">Animal activo *</label>
                    <select class="control" id="animal_id" name="animal_id" data-movement-animal data-required>
                        <option value="">Busca y selecciona un animal</option>
                        @foreach ($animals as $animal)
                            <option value="{{ $animal->id }}" data-farm-id="{{ $animal->farm_id }}" @selected((string) old('animal_id', $selectedAnimalId) === (string) $animal->id)>
                                {{ $animal->ear_tag }}{{ $animal->name ? ' · ' . $animal->name : '' }}{{ $animal->farm ? ' · ' . $animal->farm->name : '' }}{{ $animal->paddock ? ' · ' . $animal->paddock->name : ($animal->location ? ' · ' . $animal->location : '') }}
                            </option>
                        @endforeach
                    </select>
                    @error('animal_id')<span class="field-error">{{ $message }}</span>@enderror
                    @if ($animals->isEmpty())
                        <span class="field-error">Usa el buscador anterior para cargar el animal.</span>
                    @endif
                </div>
            </div>

            <div class="section-heading">
                <h2>Datos del destino</h2>
                <p>En una compra se crea la ficha del animal; en un traslado se actualiza su ubicación actual y se conserva el historial.</p>
            </div>
            <div class="field-grid" data-movement-for="purchase">
                <div class="field">
                    <label for="ear_tag">Número de arete / identificación *</label>
                    <input class="control" id="ear_tag" name="ear_tag" value="{{ old('ear_tag') }}" maxlength="40" data-required>
                    @error('ear_tag')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="name">Nombre</label>
                    <input class="control" id="name" name="name" value="{{ old('name') }}" maxlength="100">
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="species">Especie *</label>
                    <select class="control" id="species" name="species" data-species-select data-required>
                        <option value="">Selecciona la especie</option>
                        @foreach ($speciesOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('species') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('species')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="breed_id">Raza</label>
                    <select class="control" id="breed_id" name="breed_id" data-depends-on="#species" data-empty-text="Primero selecciona una especie">
                        <option value="">Sin especificar</option>
                        @foreach ($breeds as $breed)
                            <option value="{{ $breed->id }}" data-parent="{{ $breed->species }}" @selected((string) old('breed_id') === (string) $breed->id)>{{ $breed->name }}</option>
                        @endforeach
                    </select>
                    @error('breed_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="sex">Sexo *</label>
                    <select class="control" id="sex" name="sex" data-required>
                        <option value="">Selecciona el sexo</option>
                        <option value="female" @selected(old('sex') === 'female')>Hembra</option>
                        <option value="male" @selected(old('sex') === 'male')>Macho</option>
                        <option value="unknown" @selected(old('sex') === 'unknown')>Sin definir</option>
                    </select>
                    @error('sex')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="birth_date">Fecha de nacimiento</label>
                    <input class="control" id="birth_date" name="birth_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('birth_date') }}">
                    @error('birth_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field-grid" data-movement-for="purchase farm_transfer">
                <div class="field field-full">
                    <label for="to_farm_id">Finca de destino *</label>
                    <select class="control" id="to_farm_id" name="to_farm_id" data-paddock-farm data-required>
                        <option value="">Selecciona la finca</option>
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" @selected((string) old('to_farm_id', $selectedFarmId) === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
                        @endforeach
                    </select>
                    @error('to_farm_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field-grid" data-movement-for="purchase farm_transfer paddock_transfer">
                <div class="field field-full">
                    <label for="to_paddock_id">Potrero de destino</label>
                    <select class="control" id="to_paddock_id" name="to_paddock_id" data-destination-paddock data-required-for="paddock_transfer">
                        <option value="">Sin potrero asignado</option>
                        @foreach ($paddocks as $paddock)
                            <option value="{{ $paddock->id }}" @selected((string) old('to_paddock_id') === (string) $paddock->id)>{{ $paddock->code }} · {{ $paddock->name }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-paddock-error role="status"></span>
                    @error('to_paddock_id')<span class="field-error">{{ $message }}</span>@enderror
                    <span class="field-error" data-movement-for="paddock_transfer">Para registrar un traslado entre potreros, selecciona el potrero de destino.</span>
                </div>
            </div>

            <div class="field-grid" data-movement-for="purchase sale">
                <div class="field">
                    <label for="amount_purchase" data-movement-for="purchase">Precio de compra *</label>
                    <label for="amount_sale" data-movement-for="sale">Precio de venta *</label>
                    <input class="control" id="amount_purchase" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" value="{{ old('amount') }}" data-required data-movement-for="purchase">
                    <input class="control" id="amount_sale" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" value="{{ old('amount') }}" data-required data-movement-for="sale">
                    @error('amount')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field" data-movement-for="sale">
                    <label for="exit_reason">Motivo de salida *</label>
                    <select class="control" id="exit_reason" name="exit_reason" data-required>
                        <option value="">Selecciona el motivo</option>
                        @foreach ($exitReasons as $value => $label)
                            <option value="{{ $value }}" @selected(old('exit_reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('exit_reason')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field" data-movement-for="purchase sale">
                    <label for="counterparty_name" data-movement-for="purchase">Proveedor</label>
                    <label for="counterparty_name" data-movement-for="sale">Comprador</label>
                    <input class="control" id="counterparty_name" name="counterparty_name" value="{{ old('counterparty_name') }}" maxlength="150">
                    @error('counterparty_name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field" data-movement-for="purchase sale">
                    <label for="payment_method">Medio de pago</label>
                    <select class="control" id="payment_method" name="payment_method">
                        <option value="">Sin especificar</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field-grid">
                <div class="field field-full">
                    <label for="reference">Referencia / documento</label>
                    <input class="control" id="reference" name="reference" value="{{ old('reference') }}" maxlength="100">
                    @error('reference')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field field-full">
                    <label for="notes">Notas</label>
                    <textarea class="control" id="notes" name="notes" maxlength="10000">{{ old('notes') }}</textarea>
                    @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('animal-movements.index') }}">Cancelar</a>
                <button class="button button-primary" type="submit">Guardar movimiento</button>
            </div>
        </form>
    </section>
@endsection
