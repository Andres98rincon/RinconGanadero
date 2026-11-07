<?php

namespace App\Models;

use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CalendarReminder extends Model
{
    use LogsActivity, ScopesToAccessibleFarms;

    protected $fillable = ['farm_id', 'animal_id', 'created_by', 'title', 'description', 'event_date'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('calendar');
    }
}
