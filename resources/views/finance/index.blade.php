@extends('layouts.app')

@section('title', 'Finanzas y costos ganaderos')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administración ganadera</div>
            <h1>Finanzas y costos</h1>
            <p class="page-subtitle">Ingresos, egresos, flujo de caja, rentabilidad y valor estimado del inventario animal.</p>
        </div>
        <div class="detail-actions">
            <a class="button button-secondary" href="{{ route('finance.valuations.create') }}">Valorar animal</a>
            <a class="button button-primary" href="{{ route('finance.transactions.create') }}"><span aria-hidden="true">+</span> Registrar movimiento</a>
        </div>
    </div>

    <section class="stats-grid" aria-label="Resumen financiero del período">
        <div class="stat"><span class="stat-label">Ingresos registrados</span><strong class="stat-value">${{ number_format((float) $summary->income, 2, ',', '.') }}</strong></div>
        <div class="stat"><span class="stat-label">Gastos registrados</span><strong class="stat-value">${{ number_format((float) $summary->expenses, 2, ',', '.') }}</strong></div>
        <div class="stat"><span class="stat-label">Flujo neto del período</span><strong class="stat-value">${{ number_format((float) $summary->balance, 2, ',', '.') }}</strong></div>
        <div class="stat"><span class="stat-label">Valor estimado del hato activo</span><strong class="stat-value">${{ number_format((float) $inventorySummary->inventory_value, 2, ',', '.') }}</strong></div>
    </section>
    <p class="page-subtitle finance-caption">
        Rentabilidad del período: ingresos menos gastos registrados. Inventario valorado:
        {{ number_format((int) $inventorySummary->valued_animals) }} de {{ number_format($activeAnimalCount) }} animales activos.
        Las valoraciones no se cuentan como ingresos ni como flujo de caja.
    </p>

    @if (session('valuation_comparison'))
        @php($comparison = session('valuation_comparison'))
        <section class="valuation-comparison" aria-label="Comparación de valoración">
            <div><span class="eyebrow">Valoración registrada</span><h2>{{ $comparison['animal_name'] }}</h2><p>Comparación con la última valoración disponible.</p></div>
            <div class="valuation-comparison-values">
                <div><span>Anterior{{ $comparison['previous_date'] ? ' · '.\Carbon\CarbonImmutable::parse($comparison['previous_date'])->format('d/m/Y') : '' }}</span><strong>{{ $comparison['previous_amount'] === null ? 'Sin registro' : '$'.number_format($comparison['previous_amount'], 2, ',', '.') }}</strong></div>
                <div><span>Nuevo · {{ \Carbon\CarbonImmutable::parse($comparison['valuation_date'])->format('d/m/Y') }}</span><strong>${{ number_format($comparison['new_amount'], 2, ',', '.') }}</strong></div>
                <div class="valuation-variation {{ ($comparison['variation'] ?? 0) >= 0 ? 'positive' : 'negative' }}"><span>Variación económica</span><strong>{{ $comparison['variation'] === null ? 'Primera valoración' : (($comparison['variation'] >= 0 ? '+' : '').'$'.number_format(abs($comparison['variation']), 2, ',', '.')) }}</strong>@if ($comparison['variation_percent'] !== null)<small>{{ $comparison['variation_percent'] >= 0 ? '+' : '' }}{{ number_format($comparison['variation_percent'], 2, ',', '.') }}%</small>@endif</div>
            </div>
        </section>
    @endif

    <form class="filter-bar" method="GET" action="{{ route('finance.index') }}">
        <div class="field">
            <label for="from">Desde</label>
            <input class="control" id="from" type="date" name="from" value="{{ $filters['from'] }}" max="{{ today()->toDateString() }}" required>
        </div>
        <div class="field">
            <label for="to">Hasta</label>
            <input class="control" id="to" type="date" name="to" value="{{ $filters['to'] }}" max="{{ today()->toDateString() }}" required>
        </div>
        <select class="filter-control" name="farm_id" aria-label="Filtrar por finca">
            <option value="">Todas las fincas</option>
            @foreach ($farms as $farm)
                <option value="{{ $farm->id }}" @selected((string) ($filters['farm_id'] ?? '') === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="type" aria-label="Filtrar por tipo de movimiento">
            <option value="">Ingresos y gastos</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="category" aria-label="Filtrar por categoría">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $type => $typeCategories)
                @foreach ($typeCategories as $value => $label)
                    @continue(in_array($value, \App\Models\FinancialTransaction::MOVEMENT_ONLY_CATEGORIES, true))
                    <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            @endforeach
        </select>
        @if (! empty($filters['animal_id']))
            <input type="hidden" name="animal_id" value="{{ $filters['animal_id'] }}">
        @endif
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar descripción, referencia o animal" aria-label="Buscar movimiento">
        <button class="button button-secondary" type="submit">Filtrar</button>
        <a class="button button-secondary" href="{{ route('finance.index') }}">Limpiar</a>
    </form>

    <div class="detail-actions finance-report-shortcuts">
        <a class="button button-secondary" href="{{ route('reports.index', ['report' => 'finance', 'from' => now()->startOfMonth()->toDateString(), 'to' => today()->toDateString(), 'farm_id' => $filters['farm_id'] ?? null]) }}">Generar reporte mensual</a>
        <a class="button button-secondary" href="{{ route('reports.index', ['report' => 'finance', 'from' => now()->startOfYear()->toDateString(), 'to' => today()->toDateString(), 'farm_id' => $filters['farm_id'] ?? null]) }}">Generar reporte anual</a>
    </div>

    <div class="finance-report-grid">
        <section class="table-wrap" aria-label="Flujo de caja por fecha">
            <div class="farm-animals-heading">
                <div><h2>Flujo de caja</h2><p>Movimientos reales registrados agrupados por fecha.</p></div>
            </div>
            @if ($cashFlow->isEmpty())
                <div class="empty-state"><strong>Sin movimientos en el período.</strong>Al registrar ingresos y gastos aparecerán aquí.</div>
            @else
                <table class="data-table">
                    <thead><tr><th>Fecha</th><th>Ingresos</th><th>Gastos</th><th>Flujo neto</th></tr></thead>
                    <tbody>
                        @foreach ($cashFlow as $day)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($day->transaction_date)->format('d/m/Y') }}</td>
                                <td>${{ number_format((float) $day->income, 2, ',', '.') }}</td>
                                <td>${{ number_format((float) $day->expenses, 2, ',', '.') }}</td>
                                <td><strong>${{ number_format((float) $day->balance, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="pagination">
                    <span>Mostrando {{ $cashFlow->firstItem() }}–{{ $cashFlow->lastItem() }} de {{ $cashFlow->total() }} fechas</span>
                    {{ $cashFlow->onEachSide(1)->links() }}
                </div>
            @endif
        </section>

        <section class="table-wrap" aria-label="Totales por categoría">
            <div class="farm-animals-heading">
                <div><h2>Movimientos por categoría</h2><p>Totales según los filtros aplicados.</p></div>
            </div>
            @if ($categoryTotals->isEmpty())
                <div class="empty-state"><strong>Sin categorías todavía.</strong></div>
            @else
                <table class="data-table">
                    <thead><tr><th>Tipo</th><th>Categoría</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach ($categoryTotals as $categoryTotal)
                            <tr>
                                <td><span class="tag {{ $categoryTotal->type === 'income' ? 'tag-active' : 'tag-warning' }}">{{ $types[$categoryTotal->type] }}</span></td>
                                <td>{{ $categories[$categoryTotal->type][$categoryTotal->category] ?? $categoryTotal->category }}</td>
                                <td><strong>${{ number_format((float) $categoryTotal->total, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <div class="section-heading farm-animals-heading">
        <div><h2>Costos y rentabilidad por finca</h2><p>Resultado calculado como ingresos menos gastos atribuibles a cada finca.</p></div>
    </div>
    <section class="table-wrap" aria-label="Costos y rentabilidad por finca">
        @if ($farmProfitability->isEmpty())
            <div class="empty-state"><strong>Sin movimientos para comparar fincas.</strong>Registra movimientos asociados a una finca para ver su rentabilidad.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Finca</th><th>Ingresos</th><th>Costos</th><th>Rentabilidad neta</th></tr></thead>
                <tbody>
                    @foreach ($farmProfitability as $farmResult)
                        <tr>
                            <td>{{ $farmResult->farm_name }}</td>
                            <td>${{ number_format((float) $farmResult->income, 2, ',', '.') }}</td>
                            <td>${{ number_format((float) $farmResult->expenses, 2, ',', '.') }}</td>
                            <td><strong>${{ number_format((float) $farmResult->balance, 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <div class="section-heading farm-animals-heading">
        <div><h2>Costos y rentabilidad por animal</h2><p>Ingresos y egresos asociados directamente a cada animal.</p></div>
    </div>
    <section class="table-wrap" aria-label="Costos y rentabilidad por animal">
        @if ($animalProfitability->isEmpty())
            <div class="empty-state"><strong>Sin movimientos asociados a animales.</strong>Asocia un ingreso o gasto a un animal para consultar su rentabilidad.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Animal</th><th>Ingresos</th><th>Costos</th><th>Rentabilidad neta</th></tr></thead>
                <tbody>
                    @foreach ($animalProfitability as $animalResult)
                        <tr>
                            <td><a class="animal-link" href="{{ route('animals.show', $animalResult->animal_id) }}">{{ $animalResult->ear_tag }}</a><span class="animal-sub">{{ $animalResult->animal_name ?: 'Sin nombre' }}</span></td>
                            <td>${{ number_format((float) $animalResult->income, 2, ',', '.') }}</td>
                            <td>${{ number_format((float) $animalResult->expenses, 2, ',', '.') }}</td>
                            <td><strong>${{ number_format((float) $animalResult->balance, 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <div class="finance-report-grid">
        <section class="table-wrap" aria-label="Valor estimado por finca">
            <div class="farm-animals-heading">
                <div><h2>Inventario valorado por finca</h2><p>Última valoración vigente de cada animal activo.</p></div>
            </div>
            @if ($inventoryByFarm->isEmpty())
                <div class="empty-state"><strong>No hay animales activos.</strong></div>
            @else
                <table class="data-table">
                    <thead><tr><th>Finca</th><th>Animales valorados</th><th>Valor estimado</th></tr></thead>
                    <tbody>
                        @foreach ($inventoryByFarm as $farmInventory)
                            <tr>
                                <td>{{ $farmInventory->farm_name }}</td>
                                <td>{{ number_format((int) $farmInventory->valued_animals) }}</td>
                                <td><strong>${{ number_format((float) $farmInventory->inventory_value, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
        <section class="table-wrap" aria-label="Animales con mayor valor estimado">
            <div class="farm-animals-heading">
                <div><h2>Animales con mayor valor</h2><p>Valoraciones vigentes al día de hoy.</p></div>
            </div>
            @if ($inventory->isEmpty())
                <div class="empty-state"><strong>Inventario aún no valorado.</strong>Registra estimaciones para calcular el valor del hato.</div>
            @else
                <table class="data-table">
                    <thead><tr><th>Animal</th><th>Finca</th><th>Valor estimado</th></tr></thead>
                    <tbody>
                        @foreach ($inventory as $valuedAnimal)
                            <tr>
                                <td><a class="animal-link" href="{{ route('animals.show', $valuedAnimal) }}">{{ $valuedAnimal->ear_tag }}</a><span class="animal-sub">{{ $valuedAnimal->name ?: 'Sin nombre' }}</span></td>
                                <td>{{ $valuedAnimal->farm?->name ?? 'Sin finca asignada' }}</td>
                                <td><strong>${{ number_format((float) $valuedAnimal->current_valuation, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <div class="section-heading farm-animals-heading">
        <div><h2>Movimientos financieros</h2><p>{{ number_format((int) $summary->transaction_count) }} registros en el período y filtros seleccionados.</p></div>
    </div>
    <section class="table-wrap" aria-label="Historial de movimientos financieros">
        @if ($transactions->isEmpty())
            <div class="empty-state"><strong>No hay movimientos con esos filtros.</strong>Registra un ingreso o gasto para comenzar el historial financiero.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Categoría</th><th>Finca</th><th>Animal</th><th>Método</th><th>Comprobantes</th><th>Monto</th></tr></thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                            <td><span class="tag {{ $transaction->type === 'income' ? 'tag-active' : 'tag-warning' }}">{{ $types[$transaction->type] }}</span></td>
                            <td>{{ $transaction->title }}@if ($transaction->reference)<span class="animal-sub">Ref. {{ $transaction->reference }}</span>@endif</td>
                            <td>{{ $categories[$transaction->type][$transaction->category] ?? $transaction->category }}</td>
                            <td>{{ $transaction->farm?->name ?? '—' }}</td>
                            <td>{{ $transaction->animal?->ear_tag ?? '—' }}</td>
                            <td>{{ \App\Models\FinancialTransaction::PAYMENT_METHODS[$transaction->payment_method] ?? '—' }}</td>
                            <td>@foreach ($transaction->getMedia('receipts') as $attachment)<a class="animal-link" href="{{ route('finance.transactions.attachment', [$transaction, $attachment->id]) }}">{{ $attachment->file_name }}</a>@if (! $loop->last)<br>@endif @endforeach</td>
                            <td><strong>${{ number_format((float) $transaction->amount, 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} de {{ $transactions->total() }}</span>
                {{ $transactions->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
