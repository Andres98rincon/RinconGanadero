<?php

namespace App\Models;

use Database\Factories\AnimalValuationFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AnimalValuation extends Model
{
    /** @use HasFactory<AnimalValuationFactory> */
    use HasFactory, LogsActivity, ScopesToAccessibleFarms;

    protected $fillable = [
        'animal_id',
        'amount',
        'valuation_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valuation_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('finance');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
