<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LivestockReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $event */
    public function __construct(private readonly array $event)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array { return ['database']; }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->event + ['notifiable_user_id' => $notifiable->getKey()];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array { return $this->toDatabase($notifiable); }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
