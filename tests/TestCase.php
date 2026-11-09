<?php

namespace Tests;

use App\AccessControl;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Stops the suite before RefreshDatabase runs when a cached configuration
     * (php artisan config:cache / optimize) would point tests at the real database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if ($app->configurationIsCached() || $app['config']->get('app.env') !== 'testing') {
            throw new \RuntimeException('La configuración está en caché y las pruebas usarían la base de datos real. Ejecuta "php artisan optimize:clear" antes de "php artisan test".');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::findOrCreate(AccessControl::SUPER_ADMIN_ROLE, 'web');
        $this->actingAs(User::factory()->create()->assignRole($role));
    }

    protected function logOutTestUser(): void
    {
        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();
    }
}
