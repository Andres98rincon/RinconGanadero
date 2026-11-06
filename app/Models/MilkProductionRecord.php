<?php

namespace App\Models;

use Database\Factories\MilkProductionRecordFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MilkProductionRecord extends Model
{
    /** @use HasFactory<MilkProductionRecordFactory> */
    use HasFactory, LogsActivity, ScopesToAccessibleFarms;

    protected $fillable = [
        'record_date',
        'morning_liters',
        'evening_liters',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $morningCents = (int) round((float) ($record->morning_liters ?? 0) * 100);
            $eveningCents = (int) round((float) ($record->evening_liters ?? 0) * 100);
            $record->total_liters = number_format(($morningCents + $eveningCents) / 100, 2, '.', '');
        });
    }

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'morning_liters' => 'decimal:2',
            'evening_liters' => 'decimal:2',
            'total_liters' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('milk-production');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
