<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_configured_super_administrator(): void
    {
        Config::set('services.super_admin.name', 'Andrés');
        Config::set('services.super_admin.email', 'andresatm21@gmail.com');
        Config::set('services.super_admin.password', 'A-strong-test-Password-2026!');

        (new SuperAdminSeeder)->run();

        $user = User::query()->where('email', 'andresatm21@gmail.com')->firstOrFail();

        $this->assertSame('Andrés', $user->name);
        $this->assertTrue(Hash::check('A-strong-test-Password-2026!', $user->password));
        $this->assertTrue($user->hasRole(AccessControl::SUPER_ADMIN_ROLE));
    }

    public function test_seeder_rejects_a_missing_password(): void
    {
        Config::set('services.super_admin.password', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SUPER_ADMIN_PASSWORD');

        (new SuperAdminSeeder)->run();
    }

    public function test_seeder_rejects_a_password_that_does_not_meet_the_access_policy(): void
    {
        Config::set('services.super_admin.password', 'short123');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('12 characters');

        (new SuperAdminSeeder)->run();
    }

    public function test_reseeding_an_existing_account_does_not_replace_its_password(): void
    {
        Config::set('services.super_admin.name', 'Andrés');
        Config::set('services.super_admin.email', 'andresatm21@gmail.com');
        Config::set('services.super_admin.password', 'A-strong-test-Password-2026!');

        $user = User::factory()->create([
            'email' => 'andresatm21@gmail.com',
            'password' => 'Existing-password-2026!',
        ]);

        (new SuperAdminSeeder)->run();

        $this->assertTrue(Hash::check('Existing-password-2026!', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasRole(AccessControl::SUPER_ADMIN_ROLE));
    }
}
