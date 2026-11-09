<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\CalendarReminder;
use App\Models\Farm;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    private const EVENT_TYPES = ['vaccination', 'deworming', 'treatment', 'health_check', 'weighing'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'view' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'date' => ['nullable', 'date'],
        ]);
        $view = $validated['view'] ?? 'month';
        $date = isset($validated['date']) ? CarbonImmutable::parse($validated['date']) : CarbonImmutable::today();

        [$start, $end] = match ($view) {
            'day' => [$date->startOfDay(), $date->endOfDay()],
            'week' => [$date->startOfWeek(), $date->endOfWeek()],
            default => [$date->startOfMonth()->startOfWeek(), $date->endOfMonth()->endOfWeek()],
        };

        $events = collect();

        AnimalEvent::query()
            ->with(['animal.farm'])
            ->whereIn('event_type', self::EVENT_TYPES)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (AnimalEvent $event) use ($events): void {
                if (! $event->animal || ! $event->animal->farm) {
                    return;
                }
                $events->push($this->eventItem(
                    id: 'animal-event-'.$event->id,
                    title: $event->title ?: (AnimalEventController::TYPES[$event->event_type] ?? 'Control'),
                    date: $event->due_date,
                    kind: $event->event_type,
                    kindLabel: AnimalEventController::TYPES[$event->event_type] ?? 'Control',
                    description: $event->description,
                    animal: $event->animal,
                    farm: $event->animal->farm,
                ));
            });

        Animal::query()
            ->with('farm')
            ->where('reproductive_status', 'pregnant')
            ->whereBetween('expected_calving_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (Animal $animal) use ($events): void {
                if ($animal->farm) {
                    $events->push($this->eventItem(
                        id: 'calving-'.$animal->id,
                        title: 'Parto probable · '.($animal->name ?: $animal->ear_tag),
                        date: $animal->expected_calving_date,
                        kind: 'calving',
                        kindLabel: 'Parto probable',
                        description: 'Fecha probable de parto según el estado reproductivo registrado.',
                        animal: $animal,
                        farm: $animal->farm,
                    ));
                }
            });

        CalendarReminder::query()
            ->with(['animal.farm', 'farm'])
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (CalendarReminder $reminder) use ($events): void {
                if (! $reminder->farm) {
                    return;
                }
                $events->push($this->eventItem(
                    id: 'reminder-'.$reminder->id,
                    title: $reminder->title,
                    date: $reminder->event_date,
                    kind: 'reminder',
                    kindLabel: 'Recordatorio',
                    description: $reminder->description,
                    animal: $reminder->animal,
                    farm: $reminder->farm,
                ));
            });

        $events = $events->sortBy(fn (array $event) => $event['date'].' '.$event['title'])->values();
        $eventsByDate = $events->groupBy('date');
        $navigationDate = match ($view) {
            'day' => $date,
            'week' => $date->startOfWeek(),
            default => $date->startOfMonth(),
        };
        $previous = (match ($view) {
            'day' => $navigationDate->subDay(),
            'week' => $navigationDate->subWeek(),
            default => $navigationDate->subMonthNoOverflow(),
        })->toDateString();
        $next = (match ($view) {
            'day' => $navigationDate->addDay(),
            'week' => $navigationDate->addWeek(),
            default => $navigationDate->addMonthNoOverflow(),
        })->toDateString();
        $days = collect();
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $days->push($day);
        }

        return view('calendar.index', [
            'viewMode' => $view,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'events' => $events,
            'eventsByDate' => $eventsByDate,
            'previous' => $previous,
            'next' => $next,
            'farms' => Farm::query()->orderBy('name')->get(),
            'animals' => Animal::query()->with('farm')->orderBy('ear_tag')->get(),
        ]);
    }

    public function storeReminder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $request->user()->accessibleFarmIdsQuery()))],
            'animal_id' => ['nullable', 'integer', Rule::exists('animals', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('farm_id', $request->integer('farm_id')))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['required', 'date'],
        ]);

        $farm = Farm::query()->findOrFail($validated['farm_id']);
        abort_unless($request->user()->canAccessFarm($farm->id), 403);
        if (! empty($validated['animal_id'])) {
            $animal = Animal::query()->findOrFail($validated['animal_id']);
            abort_unless($animal->farm_id === (int) $validated['farm_id'], 422, 'El animal debe pertenecer a la finca seleccionada.');
        }

        $reminder = CalendarReminder::query()->create($validated + ['created_by' => $request->user()->id]);

        return to_route('calendar.index', ['view' => 'month', 'date' => $reminder->event_date->toDateString()])
            ->with('success', 'Recordatorio agregado al calendario.');
    }

    /** @return array<string, mixed> */
    private function eventItem(string $id, string $title, mixed $date, string $kind, string $kindLabel, ?string $description, ?Animal $animal, Farm $farm): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'date' => $date->toDateString(),
            'kind' => $kind,
            'kind_label' => $kindLabel,
            'description' => $description,
            'animal_name' => $animal ? ($animal->name ?: $animal->ear_tag) : null,
            'animal_tag' => $animal?->ear_tag,
            'animal_url' => $animal ? route('animals.show', $animal) : null,
            'farm_name' => $farm->name,
        ];
    }
}
