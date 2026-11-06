<?php

namespace App\Models;

use Database\Factories\AnimalMovementFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AnimalMovement extends Model
{
    public const TYPES = [
        'purchase' => 'Compra',
        'sale' => 'Venta',
        'farm_transfer' => 'Traslado entre fincas',
        'paddock_transfer' => 'Traslado entre potreros',
    ];

    public const EXIT_REASONS = [
        'market' => 'Venta comercial',
        'breeding' => 'Mejora genética o reproducción',
        'health' => 'Salud o bienestar',
        'age' => 'Edad o baja productividad',
        'herd_adjustment' => 'Ajuste del hato',
        'other' => 'Otro',
    ];

    /** @use HasFactory<AnimalMovementFactory> */
    use HasFactory, LogsActivity, ScopesToAccessibleFarms;

    protected $fillable = [
        'movement_type',
        'movement_date',
        'animal_id',
        'animal_ear_tag',
        'from_farm_id',
        'to_farm_id',
        'from_farm_name',
        'to_farm_name',
        'from_paddock_id',
        'to_paddock_id',
        'from_location',
        'to_location',
        'amount',
        'exit_reason',
        'counterparty_name',
        'reference',
        'notes',
        'financial_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('animal-movements');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function fromFarm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'from_farm_id');
    }

    public function toFarm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'to_farm_id');
    }

    public function fromPaddock(): BelongsTo
    {
        return $this->belongsTo(Paddock::class, 'from_paddock_id');
    }

    public function toPaddock(): BelongsTo
    {
        return $this->belongsTo(Paddock::class, 'to_paddock_id');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }
}
