@extends('layouts.app')

@section('title', $farm->name)

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('farms.index') }}">Fincas</a> / {{ $farm->code }}</div>
            <h1>{{ $farm->name }}</h1>
            <p class="page-subtitle">{{ $farm->code }} · {{ $farm->municipality ?: 'Ubicación no especificada' }}{{ $farm->province ? ', ' . $farm->province : '' }}</p>
        </div>
        <div class="detail-actions">
            <a class="button button-secondary" href="{{ route('paddocks.index', ['farm_id' => $farm->id]) }}">Ver potreros</a>
            <a class="button button-secondary" href="{{ route('paddocks.create', ['farm_id' => $farm->id]) }}">Agregar potrero</a>
            <a class="button button-secondary" href="{{ route('farms.edit', $farm) }}">Editar finca</a>
            <form method="POST" action="{{ route('farms.destroy', $farm) }}" data-confirm="Archivar esta finca? Solo se pueden archivar fincas sin animales asociados.">
                @csrf
                @method('DELETE')
                <button class="button button-danger" type="submit">Archivar</button>
            </form>
        </div>
    </div>

    <div class="farm-detail-grid">
        <section class="detail-panel">
            <div class="detail-title">
                <div><h2>Datos de la finca</h2><p>Ubicación y datos de contacto.</p></div>
                <span class="tag {{ $farm->status === 'active' ? 'tag-active' : 'tag-muted' }}">{{ \App\Models\Farm::STATUSES[$farm->status] }}</span>
            </div>
            <dl class="detail-list">
                <div class="detail-item"><dt>Extensión</dt><dd>@if ($farm->area_hectares !== null){{ number_format((float) $farm->area_hectares, 2, ',', '.') }} ha ({{ number_format($farm->area_square_meters, 0, ',', '.') }} m²)@else No especificada @endif</dd></div>
                <div class="detail-item"><dt>Animales asignados</dt><dd>{{ number_format($farm->animals_count) }}</dd></div>
                <div class="detail-item"><dt>Dirección</dt><dd>{{ $farm->address ?: 'No especificada' }}</dd></div>
                <div class="detail-item"><dt>Encargado</dt><dd>{{ $farm->manager_name ?: 'Sin asignar' }}</dd></div>
                <div class="detail-item"><dt>Teléfono</dt><dd>{{ $farm->phone ?: 'No registrado' }}</dd></div>
                <div class="detail-item"><dt>Correo</dt><dd>{{ $farm->email ?: 'No registrado' }}</dd></div>
                <div class="detail-item"><dt>Coordenadas</dt><dd>{{ $farm->latitude !== null && $farm->longitude !== null ? $farm->latitude . ', ' . $farm->longitude : 'No registradas' }}</dd></div>
                @if ($farm->notes)
                    <div class="detail-item farm-notes"><dt>Notas</dt><dd>{{ $farm->notes }}</dd></div>
                @endif
            </dl>
        </section>
        <section class="detail-panel farm-location-panel">
            <div class="detail-title"><div><h2>Ubicación de la finca</h2><p>Mapa de referencia con OpenStreetMap.</p></div></div>
            @if ($farm->latitude !== null && $farm->longitude !== null)
                <div class="farm-map" data-farm-map data-map-mode="display" data-lat="{{ $farm->latitude }}" data-lng="{{ $farm->longitude }}" data-farm-name="{{ $farm->name }}" data-farm-code="{{ $farm->code }}" aria-label="Mapa de {{ $farm->name }}"></div>
            @else
                <div class="empty-state">Esta finca todavía no tiene coordenadas. <a class="animal-link" href="{{ route('farms.edit', $farm) }}">Agregar ubicación</a></div>
            @endif
        </section>
    </div>

    @can('finance.view')
    <section class="detail-panel farm-expenses-panel">
        <div class="farm-animals-heading">
            <div>
                <h2>Gastos de {{ $farm->name }}</h2>
                <p>Resumen del año {{ $expenseYear }} y evolución mensual de egresos.</p>
            </div>
            <div class="detail-actions">
                @can('reports.view')<a class="button button-secondary" href="{{ route('reports.index', ['report' => 'finance', 'from' => $expenseYear . '-01-01', 'to' => today()->toDateString(), 'farm_id' => $farm->id]) }}">Reporte anual</a>@endcan
                @can('finance.transactions.create')<a class="button button-primary" href="{{ route('finance.transactions.create', ['farm_id' => $farm->id, 'type' => 'expense']) }}">Registrar gasto</a>@endcan
            </div>
        </div>
        <div class="farm-expenses-summary">
            <span>Egresos acumulados en {{ $expenseYear }}</span>
            <strong>${{ number_format((float) $annualExpenses, 2, ',', '.') }}</strong>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Mes</th><th>Egresos</th></tr></thead>
                <tbody>
                    @foreach ($expenseEvolution as $month)
                        <tr><td>{{ $month['label'] }}</td><td><strong>${{ number_format((float) $month['amount'], 2, ',', '.') }}</strong></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <a class="button button-secondary" href="{{ route('finance.index', ['farm_id' => $farm->id, 'from' => $expenseYear . '-01-01', 'to' => today()->toDateString(), 'type' => 'expense']) }}">Ver historial y filtros de gastos</a>
    </section>
    @endcan

    <div class="section-heading farm-animals-heading">
        <div>
            <h2>Animales en esta finca</h2>
            <p>Inventario asociado a {{ $farm->name }}.</p>
        </div>
        <a class="button button-secondary" href="{{ route('animals.create', ['farm_id' => $farm->id]) }}">Registrar animal aquí</a>
    </div>
    <section class="table-wrap" aria-label="Animales de la finca">
        @if ($animals->isEmpty())
            <div class="empty-state">
                <strong>No hay animales asignados.</strong>
                Registra un animal o asígnalo a esta finca desde su ficha.
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr><th>Identificación</th><th>Especie / raza</th><th>Sexo</th><th>Potrero / corral</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    @foreach ($animals as $animal)
                        <tr>
                            <td><a class="animal-link" href="{{ route('animals.show', $animal) }}">{{ $animal->ear_tag }}</a><span class="animal-sub">{{ $animal->name ?: 'Sin nombre' }}</span></td>
                            <td>{{ \App\Models\Species::label($animal->species) }}<span class="animal-sub">{{ $animal->breed?->name ?? 'Raza sin especificar' }}</span></td>
                            <td>{{ ['female' => 'Hembra', 'male' => 'Macho', 'unknown' => 'Sin definir'][$animal->sex] ?? 'Sin definir' }}</td>
                            <td>{{ $animal->paddock?->name ?? ($animal->location ?: 'Sin asignar') }}</td>
                            <td><span class="tag {{ $animal->status === 'active' ? 'tag-active' : 'tag-muted' }}">{{ \App\Models\Animal::STATUSES[$animal->status] ?? $animal->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $animals->firstItem() }}–{{ $animals->lastItem() }} de {{ $animals->total() }}</span>
                {{ $animals->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
