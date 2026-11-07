<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialFilterRequest;
use App\Models\Animal;
use App\Models\AnimalValuation;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialDashboardController extends Controller
{
    public function index(FinancialFilterRequest $request): View
    {
        $filters = $request->validated();
        $transactions = $this->filteredTransactions($filters)
            ->with(['farm:id,name,code', 'animal:id,ear_tag,name', 'media'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $summary = $this->filteredTransactions($filters)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount WHEN type = 'expense' THEN -amount ELSE 0 END), 0) as balance")
            ->selectRaw('COUNT(*) as transaction_count')
            ->first();

        $cashFlow = $this->filteredTransactions($filters)
            ->toBase()
            ->select('transaction_date')
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income")
            ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expenses")
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount WHEN type = 'expense' THEN -amount ELSE 0 END) as balance")
            ->groupBy('transaction_date')
            ->orderByDesc('transaction_date')
            ->paginate(15, ['*'], 'flow_page')
            ->withQueryString();

        $categoryTotals = $this->filteredTransactions($filters)
            ->toBase()
            ->select('type', 'category')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('type', 'category')
            ->orderByDesc('total')
            ->get();

        $farmProfitability = $this->filteredTransactions($filters)
            ->leftJoin('farms', 'financial_transactions.farm_id', '=', 'farms.id')
            ->select('financial_transactions.farm_id')
            ->selectRaw("COALESCE(farms.name, 'Sin finca asignada') as farm_name")
            ->selectRaw("SUM(CASE WHEN financial_transactions.type = 'income' THEN financial_transactions.amount ELSE 0 END) as income")
            ->selectRaw("SUM(CASE WHEN financial_transactions.type = 'expense' THEN financial_transactions.amount ELSE 0 END) as expenses")
            ->selectRaw("SUM(CASE WHEN financial_transactions.type = 'income' THEN financial_transactions.amount WHEN financial_transactions.type = 'expense' THEN -financial_transactions.amount ELSE 0 END) as balance")
            ->groupBy('financial_transactions.farm_id', 'farms.name')
            ->orderByDesc('expenses')
            ->get();

        $animalProfitability = $this->filteredTransactions($filters)
            ->whereNotNull('financial_transactions.animal_id')
            ->leftJoin('animals', 'financial_transactions.animal_id', '=', 'animals.id')
            ->select('financial_transactions.animal_id')
            ->selectRaw('animals.ear_tag, animals.name as animal_name')
            ->selectRaw("SUM(CASE WHEN financial_transactions.type = 'income' THEN financial_transactions.amount ELSE 0 END) as income")
            ->selectRaw("SUM(CASE WHEN financial_transactions.type = 'expense' THEN financial_transactions.amount ELSE 0 END) as expenses")
            ->selectRaw("SUM(CASE WHEN financial_transactions.type = 'income' THEN financial_transactions.amount WHEN financial_transactions.type = 'expense' THEN -financial_transactions.amount ELSE 0 END) as balance")
            ->groupBy('financial_transactions.animal_id', 'animals.ear_tag', 'animals.name')
            ->orderByDesc('expenses')
            ->limit(20)
            ->get();

        $currentValuations = $this->currentAnimalValuations($filters);
        $inventorySummary = DB::query()
            ->fromSub(clone $currentValuations, 'active_animals')
            ->selectRaw('COALESCE(SUM(current_valuation), 0) as inventory_value')
            ->selectRaw('COUNT(current_valuation) as valued_animals')
            ->first();

        $inventory = (clone $currentValuations)
            ->with('farm:id,name')
            ->whereHas('valuations', fn (Builder $query) => $query
                ->where('valuation_date', '<', today()->addDay()->toDateString()))
            ->orderByDesc('current_valuation')
            ->limit(10)
            ->get();

        $inventoryByFarm = DB::query()
            ->fromSub(clone $currentValuations, 'active_animals')
            ->leftJoin('farms', 'active_animals.farm_id', '=', 'farms.id')
            ->select('active_animals.farm_id')
            ->selectRaw("COALESCE(farms.name, 'Sin finca asignada') as farm_name")
            ->selectRaw('COALESCE(SUM(active_animals.current_valuation), 0) as inventory_value')
            ->selectRaw('COUNT(active_animals.current_valuation) as valued_animals')
            ->groupBy('active_animals.farm_id', 'farms.name')
            ->orderByDesc('inventory_value')
            ->get();

        $activeAnimalCount = Animal::query()
            ->where('status', 'active')
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('farm_id', $farmId))
            ->when($filters['animal_id'] ?? null, fn (Builder $query, int|string $animalId) => $query->whereKey($animalId))
            ->count();

        return view('finance.index', [
            'filters' => $filters,
            'transactions' => $transactions,
            'summary' => $summary,
            'cashFlow' => $cashFlow,
            'categoryTotals' => $categoryTotals,
            'farmProfitability' => $farmProfitability,
            'animalProfitability' => $animalProfitability,
            'inventorySummary' => $inventorySummary,
            'activeAnimalCount' => $activeAnimalCount,
            'inventory' => $inventory,
            'inventoryByFarm' => $inventoryByFarm,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
            'types' => FinancialTransaction::TYPES,
            'categories' => FinancialTransaction::CATEGORIES,
        ]);
    }

    /**
     * @param  array{from: string, to: string, farm_id?: int|string|null, animal_id?: int|string|null, type?: string|null, q?: string|null}  $filters
     * @return Builder<FinancialTransaction>
     */
    private function filteredTransactions(array $filters): Builder
    {
        return FinancialTransaction::query()
            ->where('financial_transactions.transaction_date', '>=', $filters['from'])
            ->where('financial_transactions.transaction_date', '<', Carbon::parse($filters['to'])->addDay()->toDateString())
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('financial_transactions.farm_id', $farmId))
            ->when($filters['animal_id'] ?? null, fn (Builder $query, int|string $animalId) => $query->where('financial_transactions.animal_id', $animalId))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('financial_transactions.type', $type))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('financial_transactions.category', $category))
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('financial_transactions.title', 'like', "%{$search}%")
                        ->orWhere('financial_transactions.reference', 'like', "%{$search}%")
                        ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('animal', fn (Builder $animalQuery) => $animalQuery
                            ->where('ear_tag', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * @param  array{farm_id?: int|string|null, animal_id?: int|string|null}  $filters
     * @return Builder<Animal>
     */
    private function currentAnimalValuations(array $filters): Builder
    {
        return Animal::query()
            ->where('status', 'active')
            ->when($filters['farm_id'] ?? null, fn (Builder $query, int|string $farmId) => $query->where('farm_id', $farmId))
            ->when($filters['animal_id'] ?? null, fn (Builder $query, int|string $animalId) => $query->whereKey($animalId))
            ->select(['animals.id', 'animals.farm_id', 'animals.ear_tag', 'animals.name', 'animals.species'])
            ->addSelect([
                'current_valuation' => AnimalValuation::query()
                    ->select('amount')
                    ->whereColumn('animal_id', 'animals.id')
                    ->where('valuation_date', '<', today()->addDay()->toDateString())
                    ->orderByDesc('valuation_date')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);
    }
}
