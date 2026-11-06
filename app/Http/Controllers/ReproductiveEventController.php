<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReproductiveEventRequest;
use App\Models\Animal;
use App\Models\ReproductiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReproductiveEventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', Rule::in(array_keys(ReproductiveEvent::TYPES))],
        ]);

        $events = ReproductiveEvent::query()
            ->with(['animal:id,ear_tag,name,farm_id', 'animal.farm:id,name', 'sireAnimal:id,ear_tag,name'])
            ->withCount('calves')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->whereHas('animal', function ($query) use ($search): void {
                    $query->where('ear_tag', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['event_type'] ?? null, fn ($query, string $type) => $query->where('event_type', $type))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $cowQuery = fn () => Animal::query()->where('species', 'bovine')->where('sex', 'female');
        $stats = [
            'cows' => $cowQuery()->count(),
            'pregnant' => $cowQuery()->where('reproductive_status', 'pregnant')->count(),
            'due_soon' => $cowQuery()
                ->where('reproductive_status', 'pregnant')
                ->whereNotNull('expected_calving_date')
                ->whereDate('expected_calving_date', '<=', today()->addDays(30))
                ->count(),
            'events_this_month' => ReproductiveEvent::query()
                ->whereBetween('event_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->count(),
        ];
        $upcomingCalvings = $cowQuery()
            ->where('reproductive_status', 'pregnant')
            ->whereNotNull('expected_calving_date')
            ->whereDate('expected_calving_date', '<=', today()->addDays(30))
            ->orderBy('expected_calving_date')
            ->limit(5)
            ->get(['id', 'ear_tag', 'name', 'expected_calving_date']);

        return view('reproduction.index', [
            'events' => $events,
            'filters' => $filters,
            'stats' => $stats,
            'upcomingCalvings' => $upcomingCalvings,
            'eventTypes' => ReproductiveEvent::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        $cows = Animal::query()
            ->where('species', 'bovine')
            ->where('sex', 'female')
            ->where('status', 'active')
            ->orderBy('ear_tag')
            ->get(['id', 'ear_tag', 'name', 'farm_id']);
        $bulls = Animal::query()
            ->where('species', 'bovine')
            ->where('sex', 'male')
            ->where('status', 'active')
            ->orderBy('ear_tag')
            ->get(['id', 'ear_tag', 'name']);

        return view('reproduction.form', [
            'cows' => $cows,
            'bulls' => $bulls,
            'eventTypes' => ReproductiveEvent::TYPES,
            'selectedCowId' => $request->integer('animal_id') ?: null,
        ]);
    }

    public function store(ReproductiveEventRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $cowId = $validated['animal_id'];
        unset($validated['animal_id']);
        $calves = $validated['calves'] ?? [];
        unset($validated['calves']);

        $event = DB::transaction(function () use ($cowId, $validated, $calves): ReproductiveEvent {
            $cow = Animal::query()->lockForUpdate()->findOrFail($cowId);
            $event = $cow->reproductiveEvents()->create($validated);

            if ($event->event_type === 'calving') {
                foreach ($calves as $calfData) {
                    $calf = Animal::query()->create([
                        'ear_tag' => $calfData['ear_tag'],
                        'name' => $calfData['name'] ?? null,
                        'species' => 'bovine',
                        'sex' => $calfData['sex'],
                        'identification_method' => 'visual',
                        'birth_date' => $event->event_date,
                        'birth_weight_kg' => $calfData['birth_weight_kg'] ?? null,
                        'status' => 'active',
                        'farm_id' => $cow->farm_id,
                        'dam_id' => $cow->id,
                        'sire_id' => $event->sire_animal_id,
                        'acquisition_type' => 'born',
                        'acquisition_date' => $event->event_date,
                    ]);

                    $event->calves()->attach($calf);
                }
            }

            $cow->syncReproductiveStatus();

            return $event;
        });

        return redirect()->route('reproduction.show', $event)->with('success', 'Evento reproductivo registrado correctamente.');
    }

    public function show(ReproductiveEvent $reproduction): View
    {
        $reproduction->load(['animal.farm', 'sireAnimal', 'calves']);

        return view('reproduction.show', [
            'event' => $reproduction,
            'eventTypes' => ReproductiveEvent::TYPES,
        ]);
    }
}
