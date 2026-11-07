<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\CalendarReminder;
use App\Models\Farm;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\LivestockReminderNotification;
use App\Http\Controllers\AnimalEventController;
use Carbon\CarbonImmutable;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class LivestockNotificationService
{
    public const CATEGORIES = [
        'vaccination' => 'Vacunaciones', 'calving' => 'Partos', 'treatment' => 'Tratamientos',
        'deworming' => 'Desparasitaciones', 'weighing' => 'Pesajes', 'health_check' => 'Controles veterinarios',
        'medication_expiry' => 'Vencimiento de medicamentos', 'supply_expiry' => 'Vencimiento de insumos', 'reminder' => 'Calendario',
    ];

    public function dispatchUpcoming(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;

        User::query()->where('account_status', 'active')->with('farms')->each(function (User $user) use ($today, &$sent): void {
            $preferences = NotificationPreference::query()->firstOrCreate(['user_id' => $user->id], ['enabled' => true, 'lead_days' => 1]);
            if (! $preferences->enabled) return;
            $end = $today->addDays($preferences->lead_days);
            $farmIds = $user->hasRole(\App\AccessControl::SUPER_ADMIN_ROLE)
                ? Farm::query()->pluck('id')
                : $user->farms()->pluck('farms.id');
            if ($farmIds->isEmpty()) return;
            $allowed = $preferences->categories ?: array_keys(self::CATEGORIES);
            $events = collect();
            AnimalEvent::query()->with('animal:id,ear_tag,name,farm_id')->whereIn('event_type', ['vaccination', 'deworming', 'treatment', 'health_check', 'weighing', 'medication_expiry', 'supply_expiry'])
                ->whereIn('event_type', $allowed)->whereBetween('due_date', [$today->toDateString(), $end->toDateString()])->whereHas('animal', fn ($q) => $q->whereIn('farm_id', $farmIds))->get()
                ->each(fn (AnimalEvent $event) => $events->push($this->animalEvent($event)));
            if (in_array('calving', $allowed, true)) {
                Animal::query()->whereIn('farm_id', $farmIds)->where('reproductive_status', 'pregnant')
                    ->whereBetween('expected_calving_date', [$today->toDateString(), $end->toDateString()])->get()->each(fn (Animal $animal) => $events->push($this->calving($animal)));
            }
            if (in_array('reminder', $allowed, true)) {
                CalendarReminder::query()->whereIn('farm_id', $farmIds)->whereBetween('event_date', [$today->toDateString(), $end->toDateString()])->with('animal:id,ear_tag,name')->get()->each(fn (CalendarReminder $reminder) => $events->push($this->reminder($reminder)));
            }

            foreach ($events as $event) {
                $key = $event['kind'].':'.$event['source_id'].':'.$event['date'];
                $delivery = NotificationDelivery::firstOrCreate(
                    ['user_id' => $user->id, 'reminder_key' => $key],
                    ['farm_id' => $event['farm_id'], 'sent_at' => now()],
                );
                if (! $delivery->wasRecentlyCreated) continue;
                $user->notify(new LivestockReminderNotification($event + ['reminder_key' => $key]));
                $this->sendPush($user, $event);
                $delivery->update(['notification_id' => $user->notifications()->latest('created_at')->value('id')]);
                $sent++;
            }
        });

        return $sent;
    }

    /** @param array<string, mixed> $event */
    private function sendPush(User $user, array $event): void
    {
        $publicKey = config('services.notifications.vapid_public_key');
        $privateKey = config('services.notifications.vapid_private_key');
        if (blank($publicKey) || blank($privateKey)) return;

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.notifications.vapid_subject'),
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ]]);
        foreach ($user->pushSubscriptions()->where('enabled', true)->get() as $subscription) {
            try {
                $report = $webPush->sendOneNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]), json_encode(['title' => $event['title'], 'body' => $event['body'] ?: 'Tienes un recordatorio ganadero.', 'url' => $event['url']], JSON_THROW_ON_ERROR));
                if ($report->isSubscriptionExpired()) $subscription->delete();
                else $subscription->update(['last_used_at' => now()]);
            } catch (\Throwable) {
                // A database notification is already persisted; a temporary push error must not lose it.
            }
        }
    }

    /** @return array<string, mixed> */
    private function animalEvent(AnimalEvent $event): array
    {
        return ['kind' => $event->event_type, 'source_id' => $event->id, 'title' => $event->title ?: (AnimalEventController::TYPES[$event->event_type] ?? 'Evento ganadero'), 'body' => $event->description, 'date' => $event->due_date->toDateString(), 'farm_id' => $event->animal->farm_id, 'animal_id' => $event->animal_id, 'animal_name' => $event->animal->name ?: $event->animal->ear_tag, 'url' => route('animals.show', $event->animal_id)];
    }

    /** @return array<string, mixed> */
    private function calving(Animal $animal): array
    {
        return ['kind' => 'calving', 'source_id' => $animal->id, 'title' => 'Parto próximo', 'body' => 'Parto probable de '.$animal->ear_tag, 'date' => $animal->expected_calving_date->toDateString(), 'farm_id' => $animal->farm_id, 'animal_id' => $animal->id, 'animal_name' => $animal->name ?: $animal->ear_tag, 'url' => route('animals.show', $animal)];
    }

    /** @return array<string, mixed> */
    private function reminder(CalendarReminder $reminder): array
    {
        return ['kind' => 'reminder', 'source_id' => $reminder->id, 'title' => $reminder->title, 'body' => $reminder->description, 'date' => $reminder->event_date->toDateString(), 'farm_id' => $reminder->farm_id, 'animal_id' => $reminder->animal_id, 'animal_name' => $reminder->animal?->name ?: $reminder->animal?->ear_tag, 'url' => $reminder->animal_id ? route('animals.show', $reminder->animal_id) : route('calendar.index')];
    }
}
