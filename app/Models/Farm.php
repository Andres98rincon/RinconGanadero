<?php

namespace App\Models;

use Database\Factories\FarmFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory, LogsActivity, ScopesToAccessibleFarms, SoftDeletes;

    public const STATUSES = [
        'active' => 'Activa',
        'inactive' => 'Inactiva',
    ];

    protected $fillable = [
        'code',
        'name',
        'address',
        'municipality',
        'province',
        'latitude',
        'longitude',
        'area_hectares',
        'manager_name',
        'phone',
        'email',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function getAreaSquareMetersAttribute(): ?float
    {
        return $this->area_hectares === null ? null : (float) $this->area_hectares * 10_000;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('farms');
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    public function paddocks(): HasMany
    {
        return $this->hasMany(Paddock::class);
    }

    public function animalMovementsFrom(): HasMany
    {
        return $this->hasMany(AnimalMovement::class, 'from_farm_id');
    }

    public function animalMovementsTo(): HasMany
    {
        return $this->hasMany(AnimalMovement::class, 'to_farm_id');
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
