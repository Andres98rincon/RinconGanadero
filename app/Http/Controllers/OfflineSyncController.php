<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OfflineSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $operations = $request->validate(['operations' => ['required', 'array', 'max:50'], 'operations.*.id' => ['required', 'uuid'], 'operations.*.type' => ['required', Rule::in(['animal_event'])], 'operations.*.animal_id' => ['required', 'integer'], 'operations.*.payload' => ['required', 'array']])['operations'];
        $results = [];

        foreach ($operations as $operation) {
            $offlineId = $operation['id'];
            if (AnimalEvent::query()->where('offline_id', $offlineId)->exists()) {
                $results[] = ['id' => $offlineId, 'status' => 'synced', 'duplicate' => true];
                continue;
            }

            try {
                $animal = Animal::query()->findOrFail($operation['animal_id']);
                Gate::authorize('update', $animal);
                $payload = Validator::make($operation['payload'] + ['offline_id' => $offlineId], [
                    'event_type' => ['required', Rule::in(array_keys(AnimalEventController::TYPES))],
                    'event_date' => ['required', 'date', 'before_or_equal:today'],
                    'title' => ['required', 'string', 'max:150'],
                    'description' => ['nullable', 'string', 'max:10000'],
                    'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
                    'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                    'due_date' => ['nullable', 'date', 'after_or_equal:event_date'],
                    'offline_id' => ['required', 'uuid'],
                ])->validate();

                DB::transaction(function () use ($animal, $payload): void {
                    $event = $animal->events()->create($payload);
                    if ($event->weight_kg !== null) $animal->syncCurrentWeightFromEvents(preserveLaterSnapshot: true);
                });
                $results[] = ['id' => $offlineId, 'status' => 'synced'];
            } catch (\Illuminate\Auth\Access\AuthorizationException|\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $results[] = ['id' => $offlineId, 'status' => 'denied', 'message' => 'No tienes permiso para esta finca o animal.'];
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $results[] = ['id' => $offlineId, 'status' => 'error', 'message' => $exception->getMessage(), 'errors' => $exception->errors()];
            } catch (\Throwable $exception) {
                report($exception);
                $results[] = ['id' => $offlineId, 'status' => 'error', 'message' => 'No se pudo sincronizar el registro.'];
            }
        }

        return response()->json(['results' => $results]);
    }
}
