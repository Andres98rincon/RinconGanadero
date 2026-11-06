<?php

namespace App\Http\Controllers;

use App\AccessControl;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\AnimalValuation;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\ReproductiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $report = $filters['report'];
        $query = $this->reportQuery($report, $filters);
        $rows = $this->orderedQuery($report, $query)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (object $row): array => $this->formatRow($report, $row));

        return view('reports.index', [
            'filters' => $filters,
            'reports' => ReportFilterRequest::REPORTS,
            'report' => $report,
            'description' => $this->descriptions()[$report],
            'columns' => $this->columns($report),
            'rows' => $rows,
            'summaryCards' => $this->summaryCards($report, $this->summary($report, $filters)),
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function export(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $report = $filters['report'];
        $query = $this->orderedQuery($report, $this->reportQuery($report, $filters));
        $columns = $this->columns($report);

        return response()->streamDownload(function () use ($query, $report, $columns): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new \RuntimeException('No se pudo iniciar la exportación del reporte.');
            }

            try {
                if (fwrite($stream, "\xEF\xBB\xBF") === false
                    || fputcsv($stream, array_values($columns), ',', '"', '\\') === false) {
                    throw new \RuntimeException('No se pudo escribir el encabezado del reporte.');
                }

                foreach ($query->cursor() as $row) {
                    $values = array_map(
                        fn (string $value): string => $this->protectSpreadsheetFormula($value),
                        array_values($this->formatRow($report, $row)),
                    );

                    if (fputcsv($stream, $values, ',', '"', '\\') === false) {
                        throw new \RuntimeException('No se pudo escribir una fila del reporte.');
                    }
                }
            } finally {
                fclose($stream);
            }
        }, "reporte-{$report}-{$filters['from']}-{$filters['to']}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function reportQuery(string $report, array $filters): Builder
    {
        return match ($report) {
            'inventory' => $this->inventoryQuery($filters),
            'reproduction' => $this->reproductionQuery($filters),
            'milk' => $this->milkQuery($filters),
            'weights' => $this->weightQuery($filters),
            'movements' => $this->movementQuery($filters),
            'finance' => $this->financeQuery($filters),
        };
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function inventoryQuery(array $filters): Builder
    {
        $query = DB::table('animals')
            ->leftJoin('farms', 'animals.farm_id', '=', 'farms.id')
            ->whereNull('animals.deleted_at')
            ->where('animals.status', 'active')
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('animals.farm_id', $farmId))
            ->select([
                'animals.id',
                'animals.ear_tag',
                'animals.name',
                'animals.species',
                'animals.sex',
                'animals.current_weight_kg',
                'animals.last_weighed_at',
            ])
            ->selectRaw("COALESCE(farms.name, 'Sin finca asignada') as farm_name")
            ->selectSub(
                AnimalValuation::query()
                    ->select('amount')
                    ->whereColumn('animal_id', 'animals.id')
                    ->whereDate('valuation_date', '<=', today()->toDateString())
                    ->orderByDesc('valuation_date')
                    ->orderByDesc('id')
                    ->limit(1),
                'current_valuation',
            );

        return $this->applyFarmScope($query, 'animals.farm_id');
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function reproductionQuery(array $filters): Builder
    {
        $query = DB::table('reproductive_events')
            ->join('animals', 'reproductive_events.animal_id', '=', 'animals.id')
            ->leftJoin('farms', 'animals.farm_id', '=', 'farms.id')
            ->whereNull('animals.deleted_at')
            ->whereBetween('reproductive_events.event_date', [$filters['from'], $filters['to']])
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('animals.farm_id', $farmId))
            ->select([
                'reproductive_events.id',
                'reproductive_events.event_date',
                'reproductive_events.event_type',
                'reproductive_events.diagnosis_result',
                'reproductive_events.expected_calving_date',
                'animals.ear_tag as animal_ear_tag',
                'animals.name as animal_name',
            ])
            ->selectRaw("COALESCE(farms.name, 'Sin finca asignada') as farm_name")
            ->selectSub(
                DB::table('reproductive_event_calves')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reproductive_event_id', 'reproductive_events.id'),
                'calf_count',
            );

        return $this->applyFarmScope($query, 'animals.farm_id');
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function milkQuery(array $filters): Builder
    {
        $query = DB::table('milk_production_records')
            ->join('animals', 'milk_production_records.animal_id', '=', 'animals.id')
            ->leftJoin('farms', 'animals.farm_id', '=', 'farms.id')
            ->whereNull('animals.deleted_at')
            ->whereBetween('milk_production_records.record_date', [$filters['from'], $filters['to']])
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('animals.farm_id', $farmId))
            ->select([
                'animals.id as animal_id',
                'animals.ear_tag as animal_ear_tag',
                'animals.name as animal_name',
            ])
            ->selectRaw("COALESCE(farms.name, 'Sin finca asignada') as farm_name")
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('SUM(milk_production_records.total_liters) as total_liters')
            ->selectRaw('AVG(milk_production_records.total_liters) as average_liters')
            ->selectRaw('MAX(milk_production_records.total_liters) as maximum_daily_liters')
            ->groupBy('animals.id', 'animals.ear_tag', 'animals.name', 'farms.name');

        return $this->applyFarmScope($query, 'animals.farm_id');
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function weightQuery(array $filters): Builder
    {
        $query = DB::table('animal_events')
            ->join('animals', 'animal_events.animal_id', '=', 'animals.id')
            ->leftJoin('farms', 'animals.farm_id', '=', 'farms.id')
            ->whereNull('animals.deleted_at')
            ->where('animal_events.event_type', 'weighing')
            ->whereNotNull('animal_events.weight_kg')
            ->whereBetween('animal_events.event_date', [$filters['from'], $filters['to']])
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('animals.farm_id', $farmId))
            ->select([
                'animal_events.id',
                'animal_events.animal_id',
                'animal_events.event_date',
                'animal_events.weight_kg',
                'animal_events.description',
                'animals.ear_tag as animal_ear_tag',
                'animals.name as animal_name',
            ])
            ->selectRaw("COALESCE(farms.name, 'Sin finca asignada') as farm_name");

        return $this->applyFarmScope($query, 'animals.farm_id');
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function movementQuery(array $filters): Builder
    {
        $query = DB::table('animal_movements')
            ->leftJoin('animals', 'animal_movements.animal_id', '=', 'animals.id')
            ->leftJoin('farms as from_farms', 'animal_movements.from_farm_id', '=', 'from_farms.id')
            ->leftJoin('farms as to_farms', 'animal_movements.to_farm_id', '=', 'to_farms.id')
            ->whereBetween('animal_movements.movement_date', [$filters['from'], $filters['to']])
            ->when($filters['farm_id'] ?? null, function (Builder $query, int|string $farmId): void {
                $query->where(function (Builder $farmQuery) use ($farmId): void {
                    $farmQuery->where('animal_movements.from_farm_id', $farmId)
                        ->orWhere('animal_movements.to_farm_id', $farmId);
                });
            })
            ->select([
                'animal_movements.id',
                'animal_movements.movement_date',
                'animal_movements.movement_type',
                'animal_movements.amount',
                'animal_movements.exit_reason',
                'animal_movements.counterparty_name',
                'animal_movements.from_location',
                'animal_movements.to_location',
                'animals.name as animal_name',
            ])
            ->selectRaw('COALESCE(animals.ear_tag, animal_movements.animal_ear_tag) as animal_ear_tag')
            ->selectRaw("COALESCE(NULLIF(animal_movements.from_farm_name, ''), from_farms.name, '—') as from_farm_name")
            ->selectRaw("COALESCE(NULLIF(animal_movements.to_farm_name, ''), to_farms.name, '—') as to_farm_name");

        if (! auth()->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            $query->whereIn('animal_movements.id', AnimalMovement::query()->select('animal_movements.id'));
        }

        return $query;
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function financeQuery(array $filters): Builder
    {
        $query = DB::table('financial_transactions')
            ->leftJoin('farms', 'financial_transactions.farm_id', '=', 'farms.id')
            ->whereBetween('financial_transactions.transaction_date', [$filters['from'], $filters['to']])
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('financial_transactions.farm_id', $farmId))
            ->select([
                'farms.id as farm_id',
                'financial_transactions.type',
                'financial_transactions.category',
                'farms.name as farm_name',
            ])
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(financial_transactions.amount) as total_amount')
            ->groupBy('financial_transactions.type', 'financial_transactions.category', 'farms.id', 'farms.name');

        if (! auth()->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            $query->whereIn(
                'financial_transactions.id',
                FinancialTransaction::query()->select('financial_transactions.id'),
            );
        }

        return $query;
    }

    private function applyFarmScope(Builder $query, string $column): Builder
    {
        if (! auth()->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            $query->whereIn($column, auth()->user()->accessibleFarmIdsQuery());
        }

        return $query;
    }

    private function orderedQuery(string $report, Builder $query): Builder
    {
        return match ($report) {
            'inventory' => $query->orderBy('animals.ear_tag')->orderBy('animals.id'),
            'reproduction' => $query->orderByDesc('reproductive_events.event_date')->orderByDesc('reproductive_events.id'),
            'milk' => $query->orderByDesc('total_liters')->orderBy('animal_ear_tag'),
            'weights' => $query->orderByDesc('animal_events.event_date')->orderByDesc('animal_events.id'),
            'movements' => $query->orderByDesc('animal_movements.movement_date')->orderByDesc('animal_movements.id'),
            'finance' => $query->orderBy('financial_transactions.type')->orderBy('financial_transactions.category')->orderBy('farms.name'),
        };
    }

    /**
     * @param  array{from: string, to: string, report: string, farm_id?: int|string|null}  $filters
     */
    private function summary(string $report, array $filters): object
    {
        $query = $this->reportQuery($report, $filters);

        return match ($report) {
            'inventory' => DB::query()->fromSub(clone $query, 'current_inventory')
                ->selectRaw('COUNT(*) as animal_count')
                ->selectRaw('COUNT(current_weight_kg) as weighed_animals')
                ->selectRaw('COALESCE(AVG(current_weight_kg), 0) as average_weight')
                ->selectRaw('COALESCE(SUM(current_valuation), 0) as inventory_value')
                ->selectRaw('COUNT(current_valuation) as valued_animals')
                ->first(),
            'reproduction' => DB::query()->fromSub(clone $query, 'reproductive_report')
                ->selectRaw('COUNT(*) as event_count')
                ->selectRaw("SUM(CASE WHEN event_type = 'heat' THEN 1 ELSE 0 END) as heat_count")
                ->selectRaw("SUM(CASE WHEN event_type IN ('natural_mating', 'artificial_insemination') THEN 1 ELSE 0 END) as service_count")
                ->selectRaw("SUM(CASE WHEN event_type = 'pregnancy_diagnosis' AND diagnosis_result = 'pregnant' THEN 1 ELSE 0 END) as pregnant_count")
                ->selectRaw("SUM(CASE WHEN event_type = 'calving' THEN 1 ELSE 0 END) as calving_count")
                ->selectRaw("SUM(CASE WHEN event_type = 'abortion' THEN 1 ELSE 0 END) as abortion_count")
                ->first(),
            'milk' => DB::query()->fromSub(clone $query, 'milk_report')
                ->selectRaw('COUNT(*) as animal_count')
                ->selectRaw('COALESCE(SUM(record_count), 0) as record_count')
                ->selectRaw('COALESCE(SUM(total_liters), 0) as total_liters')
                ->selectRaw('CASE WHEN SUM(record_count) > 0 THEN SUM(total_liters) / SUM(record_count) ELSE 0 END as average_liters')
                ->first(),
            'weights' => DB::query()->fromSub(clone $query, 'weight_report')
                ->selectRaw('COUNT(*) as record_count')
                ->selectRaw('COUNT(DISTINCT animal_id) as animal_count')
                ->selectRaw('COALESCE(AVG(weight_kg), 0) as average_weight')
                ->selectRaw('COALESCE(MAX(weight_kg), 0) as maximum_weight')
                ->first(),
            'movements' => DB::query()->fromSub(clone $query, 'movement_report')
                ->selectRaw('COUNT(*) as movement_count')
                ->selectRaw("SUM(CASE WHEN movement_type = 'purchase' THEN 1 ELSE 0 END) as purchase_count")
                ->selectRaw("SUM(CASE WHEN movement_type = 'sale' THEN 1 ELSE 0 END) as sale_count")
                ->selectRaw("SUM(CASE WHEN movement_type IN ('farm_transfer', 'paddock_transfer') THEN 1 ELSE 0 END) as transfer_count")
                ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'purchase' THEN amount ELSE 0 END), 0) as purchase_amount")
                ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'sale' THEN amount ELSE 0 END), 0) as sale_amount")
                ->first(),
            'finance' => DB::query()->fromSub(clone $query, 'financial_report')
                ->selectRaw('COALESCE(SUM(transaction_count), 0) as transaction_count')
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN total_amount ELSE 0 END), 0) as income")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN total_amount ELSE 0 END), 0) as expenses")
                ->first(),
        };
    }

    /**
     * @return array<string, array{label: string, value: string, note?: string}>
     */
    private function summaryCards(string $report, object $summary): array
    {
        return match ($report) {
            'inventory' => [
                'animals' => ['label' => 'Animales activos', 'value' => number_format((int) $summary->animal_count)],
                'valued' => ['label' => 'Inventario valorado', 'value' => '$'.$this->formatNumber($summary->inventory_value), 'note' => number_format((int) $summary->valued_animals).' animales con valoración'],
                'average_weight' => ['label' => 'Peso promedio actual', 'value' => $this->formatNumber($summary->average_weight).' kg'],
                'weighed' => ['label' => 'Animales con pesaje', 'value' => number_format((int) $summary->weighed_animals)],
            ],
            'reproduction' => [
                'events' => ['label' => 'Eventos reproductivos', 'value' => number_format((int) $summary->event_count)],
                'services' => ['label' => 'Montas e inseminaciones', 'value' => number_format((int) $summary->service_count)],
                'pregnancies' => ['label' => 'Diagnósticos positivos', 'value' => number_format((int) $summary->pregnant_count)],
                'calvings' => [
                    'label' => 'Partos',
                    'value' => number_format((int) $summary->calving_count),
                    'note' => number_format((int) $summary->abortion_count)
                        .((int) $summary->abortion_count === 1 ? ' aborto registrado' : ' abortos registrados'),
                ],
            ],
            'milk' => [
                'liters' => ['label' => 'Producción total', 'value' => $this->formatNumber($summary->total_liters).' L'],
                'animals' => ['label' => 'Vacas en producción', 'value' => number_format((int) $summary->animal_count)],
                'records' => ['label' => 'Días registrados', 'value' => number_format((int) $summary->record_count)],
                'average' => ['label' => 'Promedio por registro', 'value' => $this->formatNumber($summary->average_liters).' L'],
            ],
            'weights' => [
                'records' => ['label' => 'Pesajes registrados', 'value' => number_format((int) $summary->record_count)],
                'animals' => ['label' => 'Animales pesados', 'value' => number_format((int) $summary->animal_count)],
                'average' => ['label' => 'Peso promedio', 'value' => $this->formatNumber($summary->average_weight).' kg'],
                'maximum' => ['label' => 'Mayor peso', 'value' => $this->formatNumber($summary->maximum_weight).' kg'],
            ],
            'movements' => [
                'purchases' => ['label' => 'Animales comprados', 'value' => number_format((int) $summary->purchase_count), 'note' => '$'.$this->formatNumber($summary->purchase_amount).' en compras'],
                'sales' => ['label' => 'Animales vendidos', 'value' => number_format((int) $summary->sale_count), 'note' => '$'.$this->formatNumber($summary->sale_amount).' en ventas'],
                'transfers' => ['label' => 'Traslados internos', 'value' => number_format((int) $summary->transfer_count)],
                'net' => ['label' => 'Resultado comercial', 'value' => '$'.$this->formatNumber((float) $summary->sale_amount - (float) $summary->purchase_amount), 'note' => 'Ventas menos compras'],
            ],
            'finance' => [
                'income' => ['label' => 'Ingresos', 'value' => '$'.$this->formatNumber($summary->income)],
                'expenses' => ['label' => 'Gastos', 'value' => '$'.$this->formatNumber($summary->expenses)],
                'balance' => ['label' => 'Flujo neto', 'value' => '$'.$this->formatNumber((float) $summary->income - (float) $summary->expenses)],
                'transactions' => ['label' => 'Movimientos', 'value' => number_format((int) $summary->transaction_count)],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function descriptions(): array
    {
        return [
            'inventory' => 'Existencias activas y último valor estimado vigente por animal. Es una fotografía al día de hoy; el período no modifica este informe.',
            'reproduction' => 'Celos, servicios, diagnósticos de preñez, partos y abortos registrados durante el período.',
            'milk' => 'Litros acumulados, días de registro y promedio diario por animal durante el período.',
            'weights' => 'Pesajes de control, peso observado y animal responsable de cada registro.',
            'movements' => 'Compras, ventas y traslados con origen, destino, precio y motivo de salida.',
            'finance' => 'Ingresos, egresos y flujo neto agrupados por categoría y finca.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function columns(string $report): array
    {
        return match ($report) {
            'inventory' => [
                'ear_tag' => 'Arete',
                'name' => 'Animal',
                'species' => 'Especie',
                'sex' => 'Sexo',
                'farm_name' => 'Finca',
                'current_weight_kg' => 'Peso actual (kg)',
                'last_weighed_at' => 'Último pesaje',
                'current_valuation' => 'Valor estimado',
            ],
            'reproduction' => [
                'event_date' => 'Fecha',
                'event_type' => 'Evento',
                'animal_ear_tag' => 'Arete',
                'animal_name' => 'Animal',
                'farm_name' => 'Finca',
                'diagnosis_result' => 'Diagnóstico',
                'expected_calving_date' => 'Parto probable',
                'calf_count' => 'Terneros registrados',
            ],
            'milk' => [
                'animal_ear_tag' => 'Arete',
                'animal_name' => 'Animal',
                'farm_name' => 'Finca',
                'record_count' => 'Días registrados',
                'total_liters' => 'Litros totales',
                'average_liters' => 'Promedio diario (L)',
                'maximum_daily_liters' => 'Máximo diario (L)',
            ],
            'weights' => [
                'event_date' => 'Fecha',
                'animal_ear_tag' => 'Arete',
                'animal_name' => 'Animal',
                'farm_name' => 'Finca',
                'weight_kg' => 'Peso (kg)',
                'description' => 'Observaciones',
            ],
            'movements' => [
                'movement_date' => 'Fecha',
                'movement_type' => 'Movimiento',
                'animal_ear_tag' => 'Arete',
                'animal_name' => 'Animal',
                'from_farm_name' => 'Origen',
                'to_farm_name' => 'Destino',
                'amount' => 'Precio',
                'exit_reason' => 'Motivo de salida',
                'counterparty_name' => 'Contraparte',
            ],
            'finance' => [
                'type' => 'Tipo',
                'category' => 'Categoría',
                'farm_name' => 'Finca',
                'transaction_count' => 'Movimientos',
                'total_amount' => 'Total',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function formatRow(string $report, object $row): array
    {
        return match ($report) {
            'inventory' => [
                'ear_tag' => (string) $row->ear_tag,
                'name' => $row->name ?: 'Sin nombre',
                'species' => Animal::SPECIES[$row->species] ?? 'Otro',
                'sex' => ['female' => 'Hembra', 'male' => 'Macho'][$row->sex] ?? 'Sin especificar',
                'farm_name' => $row->farm_name,
                'current_weight_kg' => $row->current_weight_kg !== null ? $this->formatNumber($row->current_weight_kg) : '—',
                'last_weighed_at' => $row->last_weighed_at ? Carbon::parse($row->last_weighed_at)->format('d/m/Y') : '—',
                'current_valuation' => $row->current_valuation !== null ? '$'.$this->formatNumber($row->current_valuation) : 'Sin valorar',
            ],
            'reproduction' => [
                'event_date' => Carbon::parse($row->event_date)->format('d/m/Y'),
                'event_type' => ReproductiveEvent::TYPES[$row->event_type] ?? $row->event_type,
                'animal_ear_tag' => $row->animal_ear_tag,
                'animal_name' => $row->animal_name ?: 'Sin nombre',
                'farm_name' => $row->farm_name ?: 'Sin finca asignada',
                'diagnosis_result' => ['pregnant' => 'Preñada', 'not_pregnant' => 'No preñada'][$row->diagnosis_result] ?? '—',
                'expected_calving_date' => $row->expected_calving_date ? Carbon::parse($row->expected_calving_date)->format('d/m/Y') : '—',
                'calf_count' => number_format((int) $row->calf_count),
            ],
            'milk' => [
                'animal_ear_tag' => $row->animal_ear_tag,
                'animal_name' => $row->animal_name ?: 'Sin nombre',
                'farm_name' => $row->farm_name ?: 'Sin finca asignada',
                'record_count' => number_format((int) $row->record_count),
                'total_liters' => $this->formatNumber($row->total_liters),
                'average_liters' => $this->formatNumber($row->average_liters),
                'maximum_daily_liters' => $this->formatNumber($row->maximum_daily_liters),
            ],
            'weights' => [
                'event_date' => Carbon::parse($row->event_date)->format('d/m/Y'),
                'animal_ear_tag' => $row->animal_ear_tag,
                'animal_name' => $row->animal_name ?: 'Sin nombre',
                'farm_name' => $row->farm_name ?: 'Sin finca asignada',
                'weight_kg' => $this->formatNumber($row->weight_kg),
                'description' => $row->description ?: '—',
            ],
            'movements' => [
                'movement_date' => Carbon::parse($row->movement_date)->format('d/m/Y'),
                'movement_type' => AnimalMovement::TYPES[$row->movement_type] ?? $row->movement_type,
                'animal_ear_tag' => $row->animal_ear_tag,
                'animal_name' => $row->animal_name ?: 'Sin nombre',
                'from_farm_name' => $this->location($row->from_farm_name, $row->from_location),
                'to_farm_name' => $this->location($row->to_farm_name, $row->to_location),
                'amount' => $row->amount !== null ? '$'.$this->formatNumber($row->amount) : '—',
                'exit_reason' => AnimalMovement::EXIT_REASONS[$row->exit_reason] ?? '—',
                'counterparty_name' => $row->counterparty_name ?: '—',
            ],
            'finance' => [
                'type' => FinancialTransaction::TYPES[$row->type] ?? $row->type,
                'category' => FinancialTransaction::CATEGORIES[$row->type][$row->category] ?? $row->category,
                'farm_name' => $row->farm_name ?: 'Sin finca asignada',
                'transaction_count' => number_format((int) $row->transaction_count),
                'total_amount' => '$'.$this->formatNumber($row->total_amount),
            ],
        };
    }

    private function location(string $farmName, ?string $location): string
    {
        return $farmName.($location ? ' · '.$location : '');
    }

    private function formatNumber(float|int|string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function protectSpreadsheetFormula(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
