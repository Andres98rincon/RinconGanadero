<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilkProductionFilterRequest;
use App\Http\Requests\MilkProductionRecordRequest;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilkProductionController extends Controller
{
    public function index(MilkProductionFilterRequest $request): View
    {
        $filters = $request->validated();

        $records = $this->filteredRecords($filters)
            ->with(['animal:id,ear_tag,name,farm_id', 'animal.farm:id,name'])
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $totals = $this->filteredRecords($filters)
            ->toBase()
            ->selectRaw('COALESCE(SUM(total_liters), 0) as liters')
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COUNT(DISTINCT animal_id) as cow_count')
            ->first();

        $dailyTotals = $this->filteredRecords($filters)
            ->toBase()
            ->select('record_date')
            ->selectRaw('SUM(total_liters) as total_liters')
            ->selectRaw('COUNT(DISTINCT animal_id) as cow_count')
            ->groupBy('record_date')
            ->orderByDesc('record_date')
            ->paginate(12, ['*'], 'daily_page')
            ->withQueryString();

        $topCows = $this->filteredRecords($filters)
            ->select('animal_id')
            ->selectRaw('SUM(total_liters) as period_liters')
            ->selectRaw('AVG(total_liters) as average_liters')
            ->selectRaw('COUNT(*) as days_recorded')
            ->with(['animal:id,ear_tag,name,farm_id', 'animal.farm:id,name'])
            ->groupBy('animal_id')
            ->orderByDesc('period_liters')
            ->limit(10)
            ->get();

        return view('production.index', [
            'filters' => $filters,
            'records' => $records,
            'dailyTotals' => $dailyTotals,
            'topCows' => $topCows,
            'totals' => $totals,
            'averagePerCowDay' => $totals->record_count > 0
                ? (float) $totals->liters / (int) $totals->record_count
                : 0,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
            'cows' => Animal::query()
                ->where('species', 'bovine')
                ->where('sex', 'female')
                ->with('farm:id,name')
                ->orderBy('ear_tag')
                ->get(['id', 'ear_tag', 'name', 'farm_id']),
        ]);
    }

    public function create(Request $request): View
    {
        $cows = $this->eligibleCows()
            ->with('farm:id,name')
            ->orderBy('ear_tag')
            ->get(['id', 'ear_tag', 'name', 'farm_id']);

        return view('production.create', [
            'cows' => $cows,
            'selectedCowId' => $request->integer('animal_id') ?: null,
        ]);
    }

    public function store(MilkProductionRecordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $animal = Animal::query()->findOrFail($validated['animal_id']);
        unset($validated['animal_id']);
        $animal->productionRecords()->create($validated);

        return redirect()->route('milk-production.index', [
            'from' => $validated['record_date'],
            'to' => $validated['record_date'],
            'animal_id' => $animal->id,
        ])->with('success', 'Producción diaria registrada correctamente.');
    }

    /**
     * @param  array{from: string, to: string, farm_id?: int|string|null, animal_id?: int|string|null, q?: string|null}  $filters
     * @return Builder<MilkProductionRecord>
     */
    private function filteredRecords(array $filters): Builder
    {
        return MilkProductionRecord::query()
            ->whereBetween('record_date', [$filters['from'], $filters['to']])
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query
                ->whereHas('animal', fn (Builder $animalQuery) => $animalQuery->where('farm_id', $farmId)))
            ->when($filters['animal_id'] ?? null, fn (Builder $query, int|string $animalId) => $query->where('animal_id', $animalId))
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->whereHas('animal', function (Builder $animalQuery) use ($search): void {
                    $animalQuery->where('ear_tag', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
    }

    /**
     * @return Builder<Animal>
     */
    private function eligibleCows(): Builder
    {
        return Animal::query()
            ->where('species', 'bovine')
            ->where('sex', 'female')
            ->where('status', 'active');
    }
}
