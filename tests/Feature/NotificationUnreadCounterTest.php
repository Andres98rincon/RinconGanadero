<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\User;
use App\Notifications\LivestockReminderNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationUnreadCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_shows_the_unread_notifications_count(): void
    {
        $user = $this->operatorWithNotifications(unread: 3, read: 2);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notificaciones: 3 sin leer')
            ->assertSee('data-unread-count', false);
    }

    public function test_layout_hides_the_counter_when_everything_is_read(): void
    {
        $user = $this->operatorWithNotifications(unread: 0, read: 2);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-unread-count', false);
    }

    public function test_opening_notifications_marks_them_as_read_and_highlights_new_ones(): void
    {
        $user = $this->operatorWithNotifications(unread: 2, read: 1);
        $otherUser = $this->operatorWithNotifications(unread: 1, read: 0);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nueva')
            ->assertDontSee('data-unread-count', false);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
    }

    private function operatorWithNotifications(int $unread, int $read): User
    {
        if (! Role::query()->where('name', AccessControl::OPERATOR_ROLE)->exists()) {
            $this->seed(RolePermissionSeeder::class);
        }

        $user = User::factory()->create();
        $user->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));

        foreach ([[$unread, null], [$read, now()]] as [$count, $readAt]) {
            for ($index = 0; $index < $count; $index++) {
                $user->notifications()->create([
                    'id' => (string) Str::uuid(),
                    'type' => LivestockReminderNotification::class,
                    'data' => ['title' => 'Vacunación pendiente', 'body' => 'Vaca de prueba', 'date' => today()->toDateString()],
                    'read_at' => $readAt,
                ]);
            }
        }

        return $user;
    }
}
