<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialTransactionRequest;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialTransactionController extends Controller
{
    public function create(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'animal_id' => ['nullable', 'integer', Rule::exists('animals', 'id')->whereNull('deleted_at')],
            'type' => ['nullable', Rule::in(array_keys(FinancialTransaction::TYPES))],
        ]);
        $search = trim($filters['q'] ?? '');
        $selectedAnimalId = $request->integer('animal_id') ?: null;
        $animals = collect();

        if ($search !== '' || $selectedAnimalId !== null) {
            $animals = Animal::query()
                ->when($selectedAnimalId, fn (Builder $query, int $animalId) => $query->whereKey($animalId))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $animalQuery) use ($search): void {
                        $animalQuery->where('ear_tag', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                ->with('farm:id,name')
                ->orderBy('ear_tag')
                ->limit(50)
                ->get(['id', 'ear_tag', 'name', 'farm_id']);
        }

        return view('finance.transactions.create', [
            'animals' => $animals,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
            'search' => $search,
            'selectedAnimalId' => $selectedAnimalId,
            'selectedType' => $filters['type'] ?? null,
            'types' => FinancialTransaction::TYPES,
            'categories' => array_map(
                fn (array $categories): array => array_diff_key($categories, array_flip(FinancialTransaction::MOVEMENT_ONLY_CATEGORIES)),
                FinancialTransaction::CATEGORIES,
            ),
            'paymentMethods' => FinancialTransaction::PAYMENT_METHODS,
        ]);
    }

    public function store(StoreFinancialTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $animal = ! empty($validated['animal_id'])
            ? Animal::query()->findOrFail($validated['animal_id'])
            : null;
        $validated['farm_id'] = $animal?->farm_id ?? ($validated['farm_id'] ?? null);

        FinancialTransaction::query()->create($validated);

        return redirect()->route('finance.index', [
            'from' => $validated['transaction_date'],
            'to' => $validated['transaction_date'],
            'farm_id' => $validated['farm_id'],
            'animal_id' => $animal?->id,
        ])->with('success', 'Movimiento financiero registrado correctamente.');
    }
}
