<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Exports\AnimalExport;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_security_headers(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_push_subscriptions_only_accept_browser_push_services(): void
    {
        $keys = ['p256dh' => 'public-key', 'auth' => 'auth-secret'];

        $this->postJson(route('notifications.subscribe'), ['endpoint' => 'http://169.254.169.254/latest/meta-data', 'keys' => $keys])
            ->assertUnprocessable();
        $this->postJson(route('notifications.subscribe'), ['endpoint' => 'https://internal.example.test/push', 'keys' => $keys])
            ->assertUnprocessable();
        $this->postJson(route('notifications.subscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => $keys])
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_animal_export_neutralizes_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", AnimalExport::protectSpreadsheetFormula('=HYPERLINK("http://evil")'));
        $this->assertSame("'@SUM(A1)", AnimalExport::protectSpreadsheetFormula('@SUM(A1)'));
        $this->assertSame('Lucero', AnimalExport::protectSpreadsheetFormula('Lucero'));
        $this->assertSame(12.5, AnimalExport::protectSpreadsheetFormula(12.5));
    }

    public function test_calendar_reminder_cannot_target_an_unassigned_farm(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $assignedFarm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $foreignAnimal = Animal::factory()->create(['farm_id' => $otherFarm->id]);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));
        $user->farms()->attach($assignedFarm);
        $this->actingAs($user);

        $this->post(route('calendar.reminders.store'), [
            'farm_id' => $otherFarm->id, 'title' => 'Intruso', 'event_date' => today()->toDateString(),
        ])->assertSessionHasErrors('farm_id');
        $this->post(route('calendar.reminders.store'), [
            'farm_id' => $assignedFarm->id, 'animal_id' => $foreignAnimal->id, 'title' => 'Intruso', 'event_date' => today()->toDateString(),
        ])->assertSessionHasErrors('animal_id');

        $this->assertDatabaseCount('calendar_reminders', 0);
    }
}
