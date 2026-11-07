<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnimalValuationRequest;
use App\AccessControl;
use App\Models\Animal;
use App\Models\AnimalValuation;
use App\Models\Farm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnimalValuationController extends Controller
{
    public function create(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'animal_id' => [
                'nullable',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->when(! $request->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE), fn ($query) => $query
                    ->whereIn('id', $request->user()->accessibleFarmIdsQuery())))],
        ]);
        $search = trim($filters['q'] ?? '');
        $selectedAnimalId = $request->integer('animal_id') ?: null;
        $selectedFarmId = $request->integer('farm_id') ?: null;
        $animals = collect();

        if ($search !== '' || $selectedAnimalId !== null) {
            $animals = Animal::query()
                ->where('status', 'active')
                ->when($selectedAnimalId, fn (Builder $query, int $animalId) => $query->whereKey($animalId))
                ->when($selectedFarmId, fn (Builder $query, int $farmId) => $query->where('farm_id', $farmId))
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

        $animalIds = $animals->modelKeys();
        $latestValuations = AnimalValuation::query()
            ->whereIn('animal_id', $animalIds)
            ->whereDate('valuation_date', '<=', today())
            ->orderByDesc('valuation_date')
            ->orderByDesc('id')
            ->get()
            ->unique('animal_id')
            ->keyBy('animal_id');
        $valuationHistory = $selectedAnimalId
            ? AnimalValuation::query()->where('animal_id', $selectedAnimalId)
                ->whereDate('valuation_date', '<=', today())
                ->orderByDesc('valuation_date')->orderByDesc('id')->get()
            : collect();
        $previousValuation = $valuationHistory->first();

        return view('finance.valuations.create', compact(
            'animals', 'search', 'selectedAnimalId', 'selectedFarmId', 'latestValuations',
            'valuationHistory', 'previousValuation',
        ) + ['farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    public function store(StoreAnimalValuationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        unset($validated['farm_id']);
        $previousValuation = AnimalValuation::query()
            ->where('animal_id', $validated['animal_id'])
            ->whereDate('valuation_date', '<=', $validated['valuation_date'])
            ->orderByDesc('valuation_date')
            ->orderByDesc('id')
            ->first();
        $valuation = AnimalValuation::query()->create($validated);
        $valuation->load('animal:id,farm_id,ear_tag,name');
        $variation = $previousValuation ? (float) $valuation->amount - (float) $previousValuation->amount : null;

        return redirect()->route('finance.index', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $valuation->animal->farm_id,
            'animal_id' => $valuation->animal_id,
        ])->with('success', 'Valor estimado del animal actualizado correctamente.')
            ->with('valuation_comparison', [
                'animal_id' => $valuation->animal_id,
                'animal_name' => $valuation->animal->name ?: $valuation->animal->ear_tag,
                'valuation_date' => $valuation->valuation_date->toDateString(),
                'new_amount' => (float) $valuation->amount,
                'previous_date' => $previousValuation?->valuation_date?->toDateString(),
                'previous_amount' => $previousValuation ? (float) $previousValuation->amount : null,
                'variation' => $variation,
                'variation_percent' => $previousValuation && (float) $previousValuation->amount !== 0.0
                    ? ($variation / (float) $previousValuation->amount) * 100
                    : null,
            ]);
    }
}
