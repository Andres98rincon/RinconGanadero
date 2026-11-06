<?php

namespace Database\Seeders;

use App\AccessControl;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $name = trim((string) config('services.super_admin.name'));
        $email = mb_strtolower(trim((string) config('services.super_admin.email')));
        $password = config('services.super_admin.password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Define SUPER_ADMIN_PASSWORD in the local environment before running this seeder.');
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException($validator->errors()->first());
        }

        $this->call(RolePermissionSeeder::class);

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        $user->assignRole(AccessControl::SUPER_ADMIN_ROLE);
    }
}
