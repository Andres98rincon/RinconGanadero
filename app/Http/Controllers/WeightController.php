<?php

namespace App\Http\Controllers;

use App\Http\Requests\WeightFilterRequest;
use App\Http\Requests\WeightRecordRequest;
use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\Farm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WeightController extends Controller
{
    public function estimateForm(): View
    {
        return view('weights.estimate', [
            'estimatedWeightKg' => null,
            'measurements' => [],
        ]);
    }

    public function calculateEstimate(Request $request): View
    {
        foreach (['heart_girth_cm', 'body_length_cm'] as $measurement) {
            $value = $request->input($measurement);

            if (is_string($value)) {
                $value = str_replace(',', '.', trim($value));

                if (is_numeric($value) && (float) $value >= 0.5 && (float) $value <= 3.5) {
                    $value = (string) ((float) $value * 100);
                }

                $request->merge([$measurement => $value]);
            }
        }

        $measurements = $request->validate([
            'heart_girth_cm' => ['required', 'numeric', 'min:50', 'max:350'],
            'body_length_cm' => ['required', 'numeric', 'min:50', 'max:350'],
        ], [
            'heart_girth_cm.required' => 'Ingresa el perímetro torácico.',
            'heart_girth_cm.numeric' => 'El perímetro torácico debe ser un número.',
            'heart_girth_cm.min' => 'El perímetro torácico debe ser de al menos 50 cm.',
            'heart_girth_cm.max' => 'El perímetro torácico no puede superar 350 cm.',
            'body_length_cm.required' => 'Ingresa el largo corporal.',
            'body_length_cm.numeric' => 'El largo corporal debe ser un número.',
            'body_length_cm.min' => 'El largo corporal debe ser de al menos 50 cm.',
            'body_length_cm.max' => 'El largo corporal no puede superar 350 cm.',
        ]);

        $estimatedWeightKg = (int) round(
            (pow((float) $measurements['heart_girth_cm'], 2) * (float) $measurements['body_length_cm']) / 10840,
        );

        return view('weights.estimate', [
            'estimatedWeightKg' => $estimatedWeightKg,
            'measurements' => $measurements,
        ]);
    }

    public function index(WeightFilterRequest $request): View
    {
        $filters = $request->validated();
        $records = $this->filteredWeighings($filters)
            ->select('animal_events.*')
            ->selectSub($this->previousMeasurementQuery('weight_kg'), 'previous_weight_kg')
            ->selectSub($this->previousMeasurementQuery('event_date'), 'previous_event_date')
            ->with(['animal:id,ear_tag,name,species,farm_id,birth_date,birth_weight_kg', 'animal.farm:id,name'])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (AnimalEvent $record): AnimalEvent {
                $previousWeight = $record->previous_weight_kg ?? $record->animal->birth_weight_kg;
                $previousDate = $record->previous_event_date
                    ? Carbon::parse($record->previous_event_date)
                    : $record->animal->birth_date;

                if ($previousDate && $previousDate->greaterThan($record->event_date)) {
                    $previousWeight = null;
                    $previousDate = null;
                }

                $weightChange = $previousWeight !== null
                    ? round((float) $record->weight_kg - (float) $previousWeight, 2)
                    : null;
                $daysBetweenMeasurements = $previousDate
                    ? (int) $previousDate->diffInDays($record->event_date)
                    : 0;

                $record->setAttribute('previous_weight_kg', $previousWeight);
                $record->setAttribute('previous_event_date', $previousDate?->toDateString());
                $record->setAttribute('weight_change_kg', $weightChange);
                $record->setAttribute(
                    'average_daily_gain',
                    $weightChange !== null && $daysBetweenMeasurements > 0
                        ? round($weightChange / $daysBetweenMeasurements, 3)
                        : null,
                );

                return $record;
            });

        $stats = $this->filteredWeighings($filters)
            ->toBase()
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COUNT(DISTINCT animal_id) as animal_count')
            ->selectRaw('COALESCE(AVG(weight_kg), 0) as average_weight')
            ->selectRaw('COALESCE(MAX(weight_kg), 0) as maximum_weight')
            ->first();

        return view('weights.index', [
            'filters' => $filters,
            'records' => $records,
            'stats' => $stats,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
            'speciesOptions' => \App\Models\Species::options(),
        ]);
    }

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

        return view('weights.create', [
            'animals' => $animals,
            'search' => $search,
            'selectedAnimalId' => $selectedAnimalId,
        ]);
    }

    public function store(WeightRecordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $animal = Animal::query()->findOrFail($validated['animal_id']);
        $event = DB::transaction(function () use ($animal, $validated): AnimalEvent {
            $event = $animal->events()->create([
                'event_type' => 'weighing',
                'event_date' => $validated['event_date'],
                'title' => 'Pesaje registrado',
                'weight_kg' => $validated['weight_kg'],
                'description' => $validated['description'] ?? null,
            ]);

            $animal->syncCurrentWeightFromEvents(preserveLaterSnapshot: true);

            return $event;
        });

        return redirect()->route('weights.index', [
            'from' => $event->event_date->toDateString(),
            'to' => $event->event_date->toDateString(),
            'animal_id' => $animal->id,
        ])->with('success', 'Pesaje registrado correctamente.');
    }

    /**
     * @param  array{from: string, to: string, farm_id?: int|string|null, animal_id?: int|string|null, species?: string|null, q?: string|null}  $filters
     * @return Builder<AnimalEvent>
     */
    private function filteredWeighings(array $filters): Builder
    {
        return AnimalEvent::query()
            ->whereNotNull('weight_kg')
            ->where('event_date', '>=', $filters['from'])
            ->where('event_date', '<', Carbon::parse($filters['to'])->addDay()->toDateString())
            ->whereHas('animal', function (Builder $query) use ($filters): void {
                $query
                    ->when($filters['farm_id'] ?? null, fn (Builder $animalQuery, int|string $farmId) => $animalQuery->where('farm_id', $farmId))
                    ->when($filters['animal_id'] ?? null, fn (Builder $animalQuery, int|string $animalId) => $animalQuery->whereKey($animalId))
                    ->when($filters['species'] ?? null, fn (Builder $animalQuery, string $species) => $animalQuery->where('species', $species))
                    ->when($filters['q'] ?? null, function (Builder $animalQuery, string $search): void {
                        $animalQuery->where(function (Builder $query) use ($search): void {
                            $query->where('ear_tag', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                    });
            });
    }

    private function previousMeasurementQuery(string $column): Builder
    {
        return AnimalEvent::query()
            ->from('animal_events as previous_events')
            ->select("previous_events.{$column}")
            ->whereColumn('previous_events.animal_id', 'animal_events.animal_id')
            ->whereNotNull('previous_events.weight_kg')
            ->where(function (Builder $query): void {
                $query->whereColumn('previous_events.event_date', '<', 'animal_events.event_date')
                    ->orWhere(function (Builder $sameDayQuery): void {
                        $sameDayQuery->whereColumn('previous_events.event_date', 'animal_events.event_date')
                            ->whereColumn('previous_events.id', '<', 'animal_events.id');
                    });
            })
            ->orderByDesc('previous_events.event_date')
            ->orderByDesc('previous_events.id')
            ->limit(1);
    }
}
