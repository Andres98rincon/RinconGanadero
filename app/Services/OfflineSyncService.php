<?php

namespace App\Services;

use App\Http\Controllers\AnimalEventController;
use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\MilkProductionRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applies operations captured on a device without connection.
 *
 * Every operation carries a client generated UUID (offline_id) that is stored
 * on the created record behind a unique index, so retrying the same operation
 * (lost response, two open tabs, manual retry) never creates a second record.
 */
class OfflineSyncService
{
    public const TYPE_ANIMAL_EVENT = 'animal_event';

    public const TYPE_WEIGHT = 'weight';

    public const TYPE_MILK_PRODUCTION = 'milk_production';

    public const TYPES = [self::TYPE_ANIMAL_EVENT, self::TYPE_WEIGHT, self::TYPE_MILK_PRODUCTION];

    /** Record stored on the server (new or already received before). */
    public const STATUS_SYNCED = 'synced';

    /** The user lost access to the farm/animal or lacks the permission. */
    public const STATUS_DENIED = 'denied';

    /** The captured data does not pass the server rules. */
    public const STATUS_INVALID = 'invalid';

    /** Another record already occupies the same slot (e.g. milk for that cow and date). */
    public const STATUS_CONFLICT = 'conflict';

    /** Unexpected server failure: the device keeps the operation and retries later. */
    public const STATUS_RETRY = 'retry';

    /**
     * @param  array{id: string, type: string, animal_id: int|string, payload: array<string, mixed>}  $operation
     * @return array{id: string, status: string, duplicate?: bool, message?: string, errors?: array<string, array<int, string>>}
     */
    public function process(User $user, array $operation): array
    {
        $offlineId = $operation['id'];

        if ($this->isAlreadySynced($operation['type'], $offlineId)) {
            return ['id' => $offlineId, 'status' => self::STATUS_SYNCED, 'duplicate' => true];
        }

        try {
            $animal = Animal::query()->find($operation['animal_id']);

            if (! $animal instanceof Animal) {
                return $this->denied($offlineId, 'El animal no existe, fue archivado o no tienes acceso a su finca.');
            }

            $isStored = match ($operation['type']) {
                self::TYPE_ANIMAL_EVENT => $this->storeAnimalEvent($user, $animal, $operation['payload'], $offlineId),
                self::TYPE_WEIGHT => $this->storeWeight($user, $animal, $operation['payload'], $offlineId),
                self::TYPE_MILK_PRODUCTION => $this->storeMilkProduction($user, $animal, $operation['payload'], $offlineId),
            };

            return $isStored
                ? ['id' => $offlineId, 'status' => self::STATUS_SYNCED]
                : $this->milkConflict($offlineId);
        } catch (AuthorizationException) {
            return $this->denied($offlineId, 'No tienes permiso para registrar esta información en la finca del animal.');
        } catch (ValidationException $exception) {
            return [
                'id' => $offlineId,
                'status' => self::STATUS_INVALID,
                'message' => collect($exception->errors())->flatten()->first() ?? 'Los datos no son válidos.',
                'errors' => $exception->errors(),
            ];
        } catch (UniqueConstraintViolationException) {
            if ($this->isAlreadySynced($operation['type'], $offlineId)) {
                return ['id' => $offlineId, 'status' => self::STATUS_SYNCED, 'duplicate' => true];
            }

            return $this->milkConflict($offlineId);
        } catch (Throwable $exception) {
            report($exception);

            return ['id' => $offlineId, 'status' => self::STATUS_RETRY, 'message' => 'No se pudo sincronizar el registro. Se reintentará automáticamente.'];
        }
    }

    public function isAlreadySynced(string $type, string $offlineId): bool
    {
        $model = $type === self::TYPE_MILK_PRODUCTION ? MilkProductionRecord::class : AnimalEvent::class;

        return $model::query()->withoutGlobalScopes()->where('offline_id', $offlineId)->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeAnimalEvent(User $user, Animal $animal, array $payload, string $offlineId): bool
    {
        Gate::forUser($user)->authorize('update', $animal);

        $validated = Validator::make($payload, [
            'event_type' => ['required', Rule::in(array_keys(AnimalEventController::TYPES))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'due_date' => ['nullable', 'date', 'after_or_equal:event_date'],
        ])->validate();

        DB::transaction(function () use ($animal, $validated, $offlineId): void {
            $event = $animal->events()->create($validated + ['offline_id' => $offlineId]);

            if ($event->weight_kg !== null) {
                $animal->syncCurrentWeightFromEvents(preserveLaterSnapshot: true);
            }
        });

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeWeight(User $user, Animal $animal, array $payload, string $offlineId): bool
    {
        $this->authorizePermissionOnAnimal($user, $animal, 'weights.create');

        $validator = Validator::make($payload, [
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'description' => ['nullable', 'string', 'max:10000'],
        ], [
            'event_date.before_or_equal' => 'La fecha del pesaje no puede ser futura.',
            'weight_kg.min' => 'El peso debe ser mayor que cero.',
        ]);

        $validator->after(function ($validator) use ($animal, $payload): void {
            if ($animal->status !== 'active') {
                $validator->errors()->add('animal_id', 'El animal ya no está activo en el inventario.');
            }

            if (! $validator->errors()->has('event_date') && $animal->birth_date && $animal->birth_date->greaterThan($payload['event_date'])) {
                $validator->errors()->add('event_date', 'La fecha del pesaje no puede ser anterior al nacimiento.');
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($animal, $validated, $offlineId): void {
            $animal->events()->create([
                'offline_id' => $offlineId,
                'event_type' => 'weighing',
                'event_date' => $validated['event_date'],
                'title' => 'Pesaje registrado',
                'weight_kg' => $validated['weight_kg'],
                'description' => $validated['description'] ?? null,
            ]);

            $animal->syncCurrentWeightFromEvents(preserveLaterSnapshot: true);
        });

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return bool false when the cow already has production for that date
     */
    private function storeMilkProduction(User $user, Animal $animal, array $payload, string $offlineId): bool
    {
        $this->authorizePermissionOnAnimal($user, $animal, 'milk-production.create');

        $validator = Validator::make($payload, [
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'morning_liters' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'evening_liters' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'record_date.before_or_equal' => 'La fecha de producción no puede ser futura.',
        ]);

        $validator->after(function ($validator) use ($animal, $payload): void {
            if ($animal->species !== 'bovine' || $animal->sex !== 'female') {
                $validator->errors()->add('animal_id', 'La producción de leche solo se registra para vacas.');
            }

            if (blank($payload['morning_liters'] ?? null) && blank($payload['evening_liters'] ?? null)) {
                $validator->errors()->add('morning_liters', 'Registra al menos un ordeño para guardar la producción.');
            }
        });

        $validated = $validator->validate();

        if ($animal->productionRecords()->whereDate('record_date', $validated['record_date'])->exists()) {
            return false;
        }

        $animal->productionRecords()->create($validated + ['offline_id' => $offlineId]);

        return true;
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizePermissionOnAnimal(User $user, Animal $animal, string $permission): void
    {
        if (! $user->can($permission) || ! $user->canAccessFarm($animal->farm_id)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @return array{id: string, status: string, message: string}
     */
    private function milkConflict(string $offlineId): array
    {
        return [
            'id' => $offlineId,
            'status' => self::STATUS_CONFLICT,
            'message' => 'Ya existe un registro de producción para esta vaca en esa fecha.',
        ];
    }

    /**
     * @return array{id: string, status: string, message: string}
     */
    private function denied(string $offlineId, string $message): array
    {
        return ['id' => $offlineId, 'status' => self::STATUS_DENIED, 'message' => $message];
    }
}
