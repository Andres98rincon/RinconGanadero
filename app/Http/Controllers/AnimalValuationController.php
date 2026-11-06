<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnimalValuationRequest;
use App\Models\Animal;
use App\Models\AnimalValuation;
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
        ]);
        $search = trim($filters['q'] ?? '');
        $selectedAnimalId = $request->integer('animal_id') ?: null;
        $animals = collect();

        if ($search !== '' || $selectedAnimalId !== null) {
            $animals = Animal::query()
                ->where('status', 'active')
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

        return view('finance.valuations.create', compact('animals', 'search', 'selectedAnimalId'));
    }

    public function store(StoreAnimalValuationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $valuation = AnimalValuation::query()->create($validated);
        $valuation->load('animal:id,farm_id');

        return redirect()->route('finance.index', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'farm_id' => $valuation->animal->farm_id,
            'animal_id' => $valuation->animal_id,
        ])->with('success', 'Valor estimado del animal actualizado correctamente.');
    }
}
