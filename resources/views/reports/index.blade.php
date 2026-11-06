@extends('layouts.app')

@section('title', 'Reportes ganaderos')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Análisis de gestión</div>
            <h1>Reportes ganaderos</h1>
            <p class="page-subtitle">Consulta el desempeño del hato y descarga informes listos para compartir.</p>
        </div>
        <div class="detail-actions report-actions no-print">
            <a class="button button-secondary" href="{{ route('reports.export', $filters) }}">Descargar CSV</a>
            <button class="button button-primary" type="button" data-print>Imprimir esta página / PDF</button>
        </div>
    </div>

    <form class="filter-bar report-filter-bar no-print" method="GET" action="{{ route('reports.index') }}">
        <div class="field">
            <label for="report">Informe</label>
            <select class="control" id="report" name="report" required>
                @foreach ($reports as $value => $label)
                    <option value="{{ $value }}" @selected($report === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="from">Desde</label>
            <input class="control" id="from" type="date" name="from" value="{{ $filters['from'] }}" max="{{ today()->toDateString() }}" required>
        </div>
        <div class="field">
            <label for="to">Hasta</label>
            <input class="control" id="to" type="date" name="to" value="{{ $filters['to'] }}" max="{{ today()->toDateString() }}" required>
        </div>
        <div class="field">
            <label for="farm_id">Finca</label>
            <select class="control" id="farm_id" name="farm_id">
                <option value="">Todas las fincas</option>
                @foreach ($farms as $farm)
                    <option value="{{ $farm->id }}" @selected((string) ($filters['farm_id'] ?? '') === (string) $farm->id)>{{ $farm->code }} · {{ $farm->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="button button-secondary" type="submit">Generar reporte</button>
        <a class="button button-secondary" href="{{ route('reports.index', ['report' => $report]) }}">Limpiar filtros</a>
    </form>

    <div class="report-context">
        <div>
            <span class="eyebrow">Informe seleccionado</span>
            <h2>{{ $reports[$report] }}</h2>
            <p>{{ $description }}</p>
        </div>
        <div class="report-context-period">
            @if ($report === 'inventory')
                Corte al {{ today()->format('d/m/Y') }}
            @else
                Período {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') }}
            @endif
            @if (! empty($filters['farm_id']))
                <span>Finca: {{ $farms->firstWhere('id', (int) $filters['farm_id'])?->name ?? 'Seleccionada' }}</span>
            @endif
        </div>
    </div>

    <section class="stats-grid" aria-label="Indicadores del reporte">
        @foreach ($summaryCards as $card)
            <div class="stat">
                <span class="stat-label">{{ $card['label'] }}</span>
                <strong class="stat-value">{{ $card['value'] }}</strong>
                @if (! empty($card['note']))
                    <span class="stat-note">{{ $card['note'] }}</span>
                @endif
            </div>
        @endforeach
    </section>

    <section class="table-wrap report-table-wrap" aria-label="{{ $reports[$report] }}">
        <div class="report-table-heading">
            <div>
                <h2>Detalle del informe</h2>
                <p>{{ number_format($rows->total()) }} registros · Página {{ $rows->currentPage() }} de {{ max($rows->lastPage(), 1) }}</p>
            </div>
            <a class="button button-secondary no-print" href="{{ route('reports.export', $filters) }}">Exportar todos los registros</a>
        </div>
        @if ($rows->isEmpty())
            <div class="empty-state">
                <strong>No hay datos para los filtros seleccionados.</strong>
                Amplía el período o selecciona otra finca para consultar este reporte.
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach ($columns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $key => $label)
                                <td>{{ $row[$key] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination no-print">
                <span>Mostrando {{ $rows->firstItem() }}–{{ $rows->lastItem() }} de {{ $rows->total() }}</span>
                {{ $rows->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
    <p class="report-footnote">Los reportes reflejan los registros disponibles en el sistema y respetan la finca y el período seleccionados. Las exportaciones incluyen el conjunto completo de resultados, no solo la página visible.</p>
@endsection
