<?php

namespace App\Models\Scopes;

use App\AccessControl;
use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\AnimalEvent;
use App\Models\AnimalValuation;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\MilkProductionRecord;
use App\Models\Paddock;
use App\Models\ReproductiveEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class FarmAccessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            return;
        }

        $farmIds = $user->accessibleFarmIdsQuery();

        if ($model instanceof Farm) {
            $builder->whereIn('farms.id', clone $farmIds);

            return;
        }

        if ($model instanceof Animal || $model instanceof Paddock) {
            $builder->whereIn($model->qualifyColumn('farm_id'), clone $farmIds);

            return;
        }

        if ($model instanceof AnimalMovement) {
            $builder->where(function (Builder $query): void {
                $query->whereNotNull('animal_movements.from_farm_id')
                    ->orWhereNotNull('animal_movements.to_farm_id');
            })->where(function (Builder $query) use ($farmIds): void {
                $query->whereNull('animal_movements.from_farm_id')
                    ->orWhereIn('animal_movements.from_farm_id', clone $farmIds);
            })->where(function (Builder $query) use ($farmIds): void {
                $query->whereNull('animal_movements.to_farm_id')
                    ->orWhereIn('animal_movements.to_farm_id', clone $farmIds);
            });

            return;
        }

        if ($model instanceof FinancialTransaction) {
            $builder
                ->where(function (Builder $query) use ($farmIds): void {
                    $query->where(function (Builder $farmQuery) use ($farmIds): void {
                        $farmQuery->whereNotNull('financial_transactions.farm_id')
                            ->whereIn('financial_transactions.farm_id', clone $farmIds);
                    })->orWhere(function (Builder $animalQuery) use ($farmIds): void {
                        $animalQuery->whereNull('financial_transactions.farm_id')
                            ->whereHas('animal', fn (Builder $query) => $query
                                ->whereIn('animals.farm_id', clone $farmIds));
                    });
                })
                ->where(function (Builder $query) use ($farmIds): void {
                    $query->whereNull('financial_transactions.animal_id')
                        ->orWhereHas('animal', fn (Builder $animalQuery) => $animalQuery
                            ->whereIn('animals.farm_id', clone $farmIds));
                });

            return;
        }

        if (
            $model instanceof AnimalEvent
            || $model instanceof AnimalValuation
            || $model instanceof MilkProductionRecord
            || $model instanceof ReproductiveEvent
        ) {
            $builder->whereHas('animal', fn (Builder $query) => $query
                ->whereIn('animals.farm_id', clone $farmIds));
        }
    }
}
