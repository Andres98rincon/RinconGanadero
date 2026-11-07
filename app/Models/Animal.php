<?php

namespace App\Models;

use Database\Factories\AnimalFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Animal extends Model implements HasMedia
{
    /** @use HasFactory<AnimalFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, ScopesToAccessibleFarms, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $animal): void {
            if (blank($animal->qr_code)) {
                do {
                    $code = Str::upper(Str::random(16));
                } while (static::query()->where('qr_code', $code)->exists());

                $animal->qr_code = $code;
            }
        });
    }

    public const SPECIES = [
        'bovine' => 'Bovino',
        'goat' => 'Caprino',
        'sheep' => 'Ovino',
        'pig' => 'Porcino',
        'horse' => 'Equino',
        'buffalo' => 'Bufalino',
        'other' => 'Otro',
    ];

    public const STATUSES = [
        'active' => 'En la finca',
        'sold' => 'Vendido',
        'deceased' => 'Fallecido',
        'transferred' => 'Trasladado',
        'missing' => 'Extraviado',
    ];

    protected $fillable = [
        'ear_tag',
        'name',
        'species',
        'breed_id',
        'farm_id',
        'paddock_id',
        'sex',
        'identification_method',
        'microchip',
        'registration_number',
        'birth_date',
        'birth_weight_kg',
        'color',
        'markings',
        'purpose',
        'status',
        'reproductive_status',
        'location',
        'dam_id',
        'sire_id',
        'acquisition_type',
        'acquisition_date',
        'acquisition_cost',
        'supplier_name',
        'current_weight_kg',
        'last_weighed_at',
        'health_notes',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'acquisition_date' => 'date',
            'last_weighed_at' => 'date',
            'expected_calving_date' => 'date',
            'birth_weight_kg' => 'decimal:2',
            'current_weight_kg' => 'decimal:2',
            'acquisition_cost' => 'decimal:2',
        ];
    }

    public function getFormattedAgeAttribute(): ?string
    {
        if (! $this->birth_date) {
            return null;
        }

        $age = $this->birth_date->diff(today());
        $parts = [];

        foreach ([[$age->y, 'año', 'años'], [$age->m, 'mes', 'meses'], [$age->d, 'día', 'días']] as [$value, $singular, $plural]) {
            if ($value > 0) {
                $parts[] = $value.' '.($value === 1 ? $singular : $plural);
            }
        }

        return $parts === [] ? '0 días' : implode(', ', $parts);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('animals');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function paddock(): BelongsTo
    {
        return $this->belongsTo(Paddock::class);
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dam_id');
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sire_id');
    }

    public function offspringAsDam(): HasMany
    {
        return $this->hasMany(self::class, 'dam_id');
    }

    public function offspringAsSire(): HasMany
    {
        return $this->hasMany(self::class, 'sire_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnimalEvent::class);
    }

    public function weightEvents(): HasMany
    {
        return $this->events()->whereNotNull('weight_kg');
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(AnimalValuation::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AnimalMovement::class);
    }

    public function syncCurrentWeightFromEvents(bool $preserveLaterSnapshot = false): void
    {
        $latestWeightEvent = $this->weightEvents()
            ->latest('event_date')
            ->latest('id')
            ->first();

        if (
            $preserveLaterSnapshot
            && $latestWeightEvent
            && $this->last_weighed_at
            && $latestWeightEvent->event_date->lessThan($this->last_weighed_at)
        ) {
            return;
        }

        $this->disableLogging();

        try {
            $this->forceFill([
                'current_weight_kg' => $latestWeightEvent?->weight_kg,
                'last_weighed_at' => $latestWeightEvent?->event_date,
            ])->save();
        } finally {
            $this->enableLogging();
        }
    }

    public function reproductiveEvents(): HasMany
    {
        return $this->hasMany(ReproductiveEvent::class);
    }

    public function calvingEvents(): BelongsToMany
    {
        return $this->belongsToMany(ReproductiveEvent::class, 'reproductive_event_calves')
            ->withTimestamps();
    }

    public function milkProductionRecords(): HasMany
    {
        return $this->hasMany(MilkProductionRecord::class);
    }

    public function productionRecords(): HasMany
    {
        return $this->milkProductionRecords();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('optimized')
            ->format('webp')
            ->quality(88)
            ->width(1280)
            ->height(1280)
            ->performOnCollections('photo')
            ->nonQueued();
    }

    public function syncReproductiveStatus(): void
    {
        $latestEvent = $this->reproductiveEvents()
            ->whereIn('event_type', array_keys(ReproductiveEvent::TYPES))
            ->latest('event_date')
            ->latest('id')
            ->first();

        if (! $latestEvent) {
            return;
        }

        [$status, $expectedCalvingDate] = match ($latestEvent->event_type) {
            'heat', 'abortion' => ['open', null],
            'natural_mating', 'artificial_insemination' => ['served', null],
            'pregnancy_diagnosis' => $latestEvent->diagnosis_result === 'pregnant'
                ? ['pregnant', $latestEvent->expected_calving_date]
                : ['open', null],
            'calving' => ['lactating', null],
        };

        $this->forceFill([
            'reproductive_status' => $status,
            'expected_calving_date' => $expectedCalvingDate,
        ])->save();
    }
}
