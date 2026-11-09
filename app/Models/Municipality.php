<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Municipality extends Model
{
    protected $fillable = ['department_id', 'code', 'name'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
