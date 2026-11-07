<?php

namespace App\Models;

use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AnimalEvent extends Model
{
    use LogsActivity, ScopesToAccessibleFarms;

    protected $fillable = [
        'event_type',
        'offline_id',
        'event_date',
        'title',
        'description',
        'weight_kg',
        'cost',
        'due_date',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'due_date' => 'date',
            'weight_kg' => 'decimal:2',
            'cost' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['event_type', 'event_date', 'title', 'description', 'weight_kg', 'cost', 'due_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('animals');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
