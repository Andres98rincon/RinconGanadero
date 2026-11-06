<?php

namespace App\Models;

use Database\Factories\ReproductiveEventFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ReproductiveEvent extends Model
{
    /** @use HasFactory<ReproductiveEventFactory> */
    use HasFactory, LogsActivity, ScopesToAccessibleFarms;

    public const TYPES = [
        'heat' => 'Celo',
        'natural_mating' => 'Monta natural',
        'artificial_insemination' => 'Inseminación artificial',
        'pregnancy_diagnosis' => 'Diagnóstico de preñez',
        'abortion' => 'Aborto',
        'calving' => 'Parto',
    ];

    protected $fillable = [
        'event_type',
        'event_date',
        'sire_animal_id',
        'sire_name',
        'semen_code',
        'technician',
        'diagnosis_result',
        'expected_calving_date',
        'calving_difficulty',
        'gestation_weeks',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'expected_calving_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('reproduction');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function sireAnimal(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sire_animal_id');
    }

    public function calves(): BelongsToMany
    {
        return $this->belongsToMany(Animal::class, 'reproductive_event_calves')
            ->withTimestamps();
    }
}
