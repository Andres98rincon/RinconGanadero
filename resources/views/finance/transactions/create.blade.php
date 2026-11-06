@extends('layouts.app')

@section('title', 'Registrar ingreso o gasto')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('finance.index') }}">Finanzas y costos</a> / Nuevo movimiento</div>
            <h1>Registrar ingreso o gasto</h1>
            <p class="page-subtitle">Captura el movimiento con su fecha, categoría y finca o animal asociado.</p>
        </div>
    </div>

    <section class="form-panel">
        <form class="filter-bar" method="GET" action="{{ route('finance.transactions.create') }}">
            <div class="field filter-search">
                <label for="q">Buscar animal para asociar</label>
                <input class="control" id="q" type="search" name="q" value="{{ $search }}" placeholder="Arete o nombre" maxlength="100">
            </div>
            <button class="button button-secondary" type="submit">Buscar</button>
        </form>

        @if ($search || $selectedAnimalId)
            @if ($animals->isEmpty())
                <div class="empty-state"><strong>No encontramos animales con esa búsqueda.</strong>Prueba con otro arete o nombre.</div>
            @endif
        @else
            <p class="page-subtitle">Para asociar un movimiento a un animal, búscalo arriba. También puedes registrar costos generales de una finca.</p>
        @endif

        <form method="POST" action="{{ route('finance.transactions.store') }}">
            @csrf
            <div class="field-grid">
                <div class="field">
                    <label for="type">Tipo de movimiento *</label>
                    <select class="control" id="type" name="type" required>
                        <option value="">Selecciona el tipo</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $selectedType) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="category">Categoría *</label>
                    <select class="control" id="category" name="category" required>
                        <option value="">Selecciona categoría</option>
                        @foreach ($categories as $type => $typeCategories)
                            <optgroup label="{{ $types[$type] }}">
                                @foreach ($typeCategories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('category')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field field-full">
                    <label for="title">Descripción *</label>
                    <input class="control" id="title" name="title" value="{{ old('title') }}" maxlength="150" required>
                    @error('title')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="amount">Monto *</label>
                    <input class="control" id="amount" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" value="{{ old('amount') }}" required>
                    @error('amount')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="transaction_date">Fecha *</label>
                    <input class="control" id="transaction_date" name="transaction_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('transaction_date', today()->toDateString()) }}" required>
                    @error('transaction_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="farm_id">Finca</label>
                    <select class="control" id="farm_id" name="farm_id">
                        <option value="">Sin finca</option>
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" @selected((string) old('farm_id') === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
                        @endforeach
                    </select>
                    @error('farm_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="animal_id">Animal</label>
                    <select class="control" id="animal_id" name="animal_id">
                        <option value="">Sin animal asociado</option>
                        @foreach ($animals as $animal)
                            <option value="{{ $animal->id }}" @selected((string) old('animal_id', $selectedAnimalId) === (string) $animal->id)>
                                {{ $animal->ear_tag }}{{ $animal->name ? ' · ' . $animal->name : '' }}{{ $animal->farm ? ' · ' . $animal->farm->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('animal_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="payment_method">Medio de pago</label>
                    <select class="control" id="payment_method" name="payment_method">
                        <option value="">Sin especificar</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="reference">Referencia</label>
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
                <a class="button button-secondary" href="{{ route('finance.index') }}">Cancelar</a>
                <button class="button button-primary" type="submit">Guardar movimiento</button>
            </div>
        </form>
    </section>
@endsection
