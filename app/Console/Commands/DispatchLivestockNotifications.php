<?php

namespace App\Console\Commands;

use App\Services\LivestockNotificationService;
use Illuminate\Console\Command;

class DispatchLivestockNotifications extends Command
{
    protected $signature = 'notifications:dispatch-livestock';
    protected $description = 'Envía recordatorios ganaderos próximos a los usuarios autorizados';

    public function handle(LivestockNotificationService $service): int
    {
        $count = $service->dispatchUpcoming();
        $this->info("{$count} notificaciones ganaderas enviadas.");

        return self::SUCCESS;
    }
}
