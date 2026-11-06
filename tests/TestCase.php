<?php

namespace Tests;

use App\AccessControl;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
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
