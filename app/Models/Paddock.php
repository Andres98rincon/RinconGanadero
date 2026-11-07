<?php

namespace App\Models;

use Database\Factories\PaddockFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Paddock extends Model
{
    /** @use HasFactory<PaddockFactory> */
    use HasFactory, LogsActivity, ScopesToAccessibleFarms, SoftDeletes;

    public const STATUSES = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ];

    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'area_hectares',
        'capacity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_hectares' => 'decimal:2',
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
            ->useLogName('paddocks');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(AnimalMovement::class, 'to_paddock_id');
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(AnimalMovement::class, 'from_paddock_id');
    }
}
