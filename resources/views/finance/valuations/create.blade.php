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
            <div class="field">
                <label for="farm_id">Finca</label>
                <select class="control" id="farm_id" name="farm_id" data-placeholder="Todas las fincas" data-allow-clear="true">
                    <option value="">Todas las fincas</option>
                    @foreach ($farms as $farm)
                        <option value="{{ $farm->id }}" @selected((string) $selectedFarmId === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
                    @endforeach
                </select>
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
                @if ($selectedFarmId)<input type="hidden" name="farm_id" value="{{ $selectedFarmId }}">@endif
                <div class="field-grid">
                    <div class="field field-full">
                        <label for="animal_id">Animal *</label>
                        <select class="control" id="animal_id" name="animal_id" required>
                            <option value="">Selecciona un animal</option>
                            @foreach ($animals as $animal)
                                <option value="{{ $animal->id }}" @selected((string) old('animal_id', $selectedAnimalId) === (string) $animal->id)>
                                    {{ $animal->ear_tag }}{{ $animal->name ? ' · ' . $animal->name : '' }}{{ $animal->farm ? ' · ' . $animal->farm->name : '' }}{{ $latestValuations->get($animal->id) ? ' · Último: $' . number_format((float) $latestValuations->get($animal->id)->amount, 2, ',', '.') : ' · Sin valoración previa' }}
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
                @if ($previousValuation)
                    <div class="valuation-previous-card field-full">
                        <div><span class="stat-label">Última valoración</span><strong>${{ number_format((float) $previousValuation->amount, 2, ',', '.') }}</strong><small>{{ $previousValuation->valuation_date->format('d/m/Y') }}</small></div>
                        <p>Al guardar el nuevo valor podrás ver la variación económica frente a este registro.</p>
                    </div>
                @endif
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ route('finance.index') }}">Cancelar</a>
                    <button class="button button-primary" type="submit">Guardar valoración</button>
                </div>
            </form>
            @if ($valuationHistory->isNotEmpty())
                <section class="valuation-history" aria-label="Historial de valoraciones">
                    <h2>Historial del animal</h2>
                    <table class="data-table"><thead><tr><th>Fecha</th><th>Valor</th><th>Observaciones</th></tr></thead><tbody>
                        @foreach ($valuationHistory as $history)
                            <tr><td>{{ $history->valuation_date->format('d/m/Y') }}</td><td><strong>${{ number_format((float) $history->amount, 2, ',', '.') }}</strong></td><td>{{ $history->notes ?: '—' }}</td></tr>
                        @endforeach
                    </tbody></table>
                </section>
            @endif
        @endif
    </section>
@endsection
