<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = ['user_id', 'farm_id', 'reminder_key', 'notification_id', 'sent_at'];

    protected function casts(): array { return ['sent_at' => 'datetime']; }
}
