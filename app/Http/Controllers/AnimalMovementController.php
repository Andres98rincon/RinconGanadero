<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnimalMovementRequest;
use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\Breed;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\Paddock;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnimalMovementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $request->user()->accessibleFarmIdsQuery()))],
            'movement_type' => ['nullable', 'in:purchase,sale,farm_transfer,paddock_transfer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $filters['from'] ??= now()->startOfMonth()->toDateString();
        $filters['to'] ??= today()->toDateString();

        $movements = $this->filteredMovements($filters)
            ->with([
                'animal:id,ear_tag,name',
                'fromFarm:id,name,code',
                'toFarm:id,name,code',
                'fromPaddock:id,name,code',
                'toPaddock:id,name,code',
            ])
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $totals = $this->filteredMovements($filters)
            ->toBase()
            ->selectRaw("COUNT(CASE WHEN movement_type = 'purchase' THEN 1 END) as purchases")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'purchase' THEN amount ELSE 0 END), 0) as purchase_amount")
            ->selectRaw("COUNT(CASE WHEN movement_type = 'sale' THEN 1 END) as sales")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'sale' THEN amount ELSE 0 END), 0) as sale_amount")
            ->selectRaw("COUNT(CASE WHEN movement_type IN ('farm_transfer', 'paddock_transfer') THEN 1 END) as transfers")
            ->first();

        return view('animal-movements.index', [
            'movements' => $movements,
            'filters' => $filters,
            'totals' => $totals,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
            'types' => AnimalMovement::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'animal_id' => ['nullable', 'integer', Rule::exists('animals', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('farm_id', $request->user()->accessibleFarmIdsQuery()))],
            'movement_type' => ['nullable', 'in:purchase,sale,farm_transfer,paddock_transfer'],
            'to_farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $request->user()->accessibleFarmIdsQuery()))],
        ]);
        $search = trim($filters['q'] ?? '');
        $selectedAnimalId = $request->integer('animal_id') ?: (int) $request->old('animal_id') ?: null;
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
                ->with(['farm:id,name,code', 'paddock:id,name,code'])
                ->orderBy('ear_tag')
                ->limit(50)
                ->get(['id', 'ear_tag', 'name', 'farm_id', 'paddock_id', 'location']);
        }

        $selectedAnimal = $animals->firstWhere('id', $selectedAnimalId);
        $selectedFarmId = $filters['to_farm_id'] ?? $request->old('to_farm_id') ?? $selectedAnimal?->farm_id;

        return view('animal-movements.create', [
            'animals' => $animals,
            'search' => $search,
            'selectedAnimalId' => $selectedAnimalId,
            'selectedFarmId' => $selectedFarmId,
            'movementType' => $filters['movement_type'] ?? $request->old('movement_type'),
            'farms' => Farm::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'paddocks' => $selectedFarmId
                ? Paddock::query()->where('farm_id', $selectedFarmId)->where('status', 'active')->orderBy('name')->get(['id', 'farm_id', 'code', 'name'])
                : collect(),
            'breeds' => Breed::query()->orderBy('species')->orderBy('name')->get(['id', 'species', 'name']),
            'types' => AnimalMovement::TYPES,
            'exitReasons' => AnimalMovement::EXIT_REASONS,
            'paymentMethods' => FinancialTransaction::PAYMENT_METHODS,
            'speciesOptions' => \App\Models\Species::options(),
        ]);
    }

    public function store(StoreAnimalMovementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $movement = DB::transaction(function () use ($validated): AnimalMovement {
            $type = $validated['movement_type'];
            $isPurchase = $type === 'purchase';
            $animal = null;
            $destinationFarm = null;
            $destinationPaddock = null;

            if ($isPurchase) {
                $destinationFarm = Farm::query()
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->findOrFail($validated['to_farm_id']);
                $destinationPaddock = ! empty($validated['to_paddock_id'])
                    ? Paddock::query()
                        ->where('status', 'active')
                        ->where('farm_id', $destinationFarm->id)
                        ->lockForUpdate()
                        ->findOrFail($validated['to_paddock_id'])
                    : null;
                $animal = Animal::query()->create([
                    'ear_tag' => $validated['ear_tag'],
                    'name' => $validated['name'] ?? null,
                    'species' => $validated['species'],
                    'breed_id' => $validated['breed_id'] ?? null,
                    'sex' => $validated['sex'],
                    'farm_id' => $destinationFarm->id,
                    'paddock_id' => $destinationPaddock?->id,
                    'location' => $destinationPaddock?->name,
                    'status' => 'active',
                    'acquisition_type' => 'purchased',
                    'acquisition_date' => $validated['movement_date'],
                    'acquisition_cost' => $validated['amount'],
                    'supplier_name' => $validated['counterparty_name'] ?? null,
                ]);
            } else {
                $animal = Animal::query()
                    ->with(['farm', 'paddock'])
                    ->whereKey($validated['animal_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($animal->status !== 'active') {
                    throw ValidationException::withMessages([
                        'animal_id' => 'Solo se pueden registrar movimientos para animales activos.',
                    ]);
                }

                if ($type === 'farm_transfer') {
                    $destinationFarm = Farm::query()
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->findOrFail($validated['to_farm_id']);
                } elseif ($type === 'paddock_transfer') {
                    $destinationFarm = $animal->farm;
                }

                $destinationPaddock = ! empty($validated['to_paddock_id'])
                    ? Paddock::query()
                        ->where('status', 'active')
                        ->where('farm_id', $destinationFarm?->id)
                        ->lockForUpdate()
                        ->findOrFail($validated['to_paddock_id'])
                    : null;

                if ($type === 'farm_transfer' && $animal->farm_id === $destinationFarm->id) {
                    throw ValidationException::withMessages([
                        'to_farm_id' => 'Selecciona una finca diferente a la ubicación actual.',
                    ]);
                }

                if (
                    $type === 'paddock_transfer'
                    && (! $animal->farm_id || $destinationPaddock?->farm_id !== $animal->farm_id || $animal->paddock_id === $destinationPaddock?->id)
                ) {
                    throw ValidationException::withMessages([
                        'to_paddock_id' => 'El potrero de destino debe ser diferente y pertenecer a la finca actual del animal.',
                    ]);
                }
            }

            $movement = AnimalMovement::query()->create([
                'movement_type' => $type,
                'movement_date' => $validated['movement_date'],
                'animal_id' => $animal->id,
                'animal_ear_tag' => $animal->ear_tag,
                'from_farm_id' => $isPurchase ? null : $animal->farm_id,
                'to_farm_id' => $type === 'sale' ? null : $destinationFarm?->id,
                'from_farm_name' => $isPurchase ? null : $animal->farm?->name,
                'to_farm_name' => $type === 'sale' ? null : $destinationFarm?->name,
                'from_paddock_id' => $isPurchase ? null : $animal->paddock_id,
                'to_paddock_id' => $type === 'sale' ? null : $destinationPaddock?->id,
                'from_location' => $isPurchase ? null : ($animal->paddock?->name ?? $animal->location),
                'to_location' => $type === 'sale' ? null : $destinationPaddock?->name,
                'amount' => in_array($type, ['purchase', 'sale'], true) ? $validated['amount'] : null,
                'exit_reason' => $validated['exit_reason'] ?? null,
                'counterparty_name' => $validated['counterparty_name'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($type === 'purchase' || $type === 'sale') {
                $financialTransaction = FinancialTransaction::query()->create([
                    'type' => $type === 'purchase' ? 'expense' : 'income',
                    'category' => $type === 'purchase' ? 'animal_purchase' : 'animal_sales',
                    'title' => ($type === 'purchase' ? 'Compra de animal ' : 'Venta de animal ').$animal->ear_tag,
                    'amount' => $validated['amount'],
                    'transaction_date' => $validated['movement_date'],
                    'farm_id' => $type === 'purchase' ? $destinationFarm?->id : $animal->farm_id,
                    'animal_id' => $animal->id,
                    'payment_method' => $validated['payment_method'] ?? null,
                    'reference' => $validated['reference'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $movement->update(['financial_transaction_id' => $financialTransaction->id]);
            }

            if ($type === 'sale') {
                $animal->update([
                    'status' => 'sold',
                    'farm_id' => null,
                    'paddock_id' => null,
                    'location' => null,
                ]);
            } elseif (in_array($type, ['farm_transfer', 'paddock_transfer'], true)) {
                $animal->update([
                    'farm_id' => $destinationFarm?->id,
                    'paddock_id' => $destinationPaddock?->id,
                    'location' => $destinationPaddock?->name,
                ]);
            }

            return $movement;
        });

        return redirect()->route('animal-movements.index', [
            'from' => $movement->movement_date->toDateString(),
            'to' => $movement->movement_date->toDateString(),
        ])->with('success', 'Movimiento registrado correctamente.');
    }

    /**
     * @param  array{from: string, to: string, farm_id?: int|string|null, movement_type?: string|null, q?: string|null}  $filters
     * @return Builder<AnimalMovement>
     */
    private function filteredMovements(array $filters): Builder
    {
        return AnimalMovement::query()
            ->where('movement_date', '>=', $filters['from'])
            ->where('movement_date', '<', Carbon::parse($filters['to'])->addDay()->toDateString())
            ->when($filters['farm_id'] ?? null, function (Builder $query, int|string $farmId): void {
                $query->where(fn (Builder $farmQuery) => $farmQuery
                    ->where('from_farm_id', $farmId)
                    ->orWhere('to_farm_id', $farmId));
            })
            ->when($filters['movement_type'] ?? null, fn (Builder $query, string $type) => $query->where('movement_type', $type))
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('animal_ear_tag', 'like', "%{$search}%")
                        ->orWhere('counterparty_name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('animal', fn (Builder $animalQuery) => $animalQuery
                            ->where('ear_tag', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            });
    }
}
