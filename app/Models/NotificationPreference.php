<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'enabled', 'lead_days', 'categories'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'lead_days' => 'integer', 'categories' => 'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
