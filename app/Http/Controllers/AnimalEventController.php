<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnimalEventController extends Controller
{
    public const TYPES = [
        'weighing' => 'Pesaje',
        'vaccination' => 'Vacunacion',
        'treatment' => 'Tratamiento',
        'health_check' => 'Revision sanitaria',
        'breeding' => 'Monta',
        'pregnancy' => 'Diagnostico de gestacion',
        'calving' => 'Parto',
        'movement' => 'Traslado',
        'purchase' => 'Compra',
        'sale' => 'Venta',
        'other' => 'Otro',
    ];

    public function store(Request $request, Animal $animal): RedirectResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'due_date' => ['nullable', 'date', 'after_or_equal:event_date'],
        ]);

        DB::transaction(function () use ($animal, $validated): void {
            $event = $animal->events()->create($validated);

            if ($event->weight_kg !== null) {
                $animal->syncCurrentWeightFromEvents(preserveLaterSnapshot: true);
            }
        });

        return redirect()->route('animals.show', $animal)->with('success', 'Evento agregado a la ficha.');
    }

    public function destroy(Animal $animal, AnimalEvent $event): RedirectResponse
    {
        abort_unless($event->animal_id === $animal->id, 404);

        DB::transaction(function () use ($animal, $event): void {
            $hadWeight = $event->weight_kg !== null;
            $event->delete();

            if ($hadWeight) {
                $animal->syncCurrentWeightFromEvents();
            }
        });

        return redirect()->route('animals.show', $animal)->with('success', 'Evento eliminado del historial.');
    }
}
