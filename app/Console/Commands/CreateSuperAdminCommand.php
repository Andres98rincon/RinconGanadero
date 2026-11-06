<?php

namespace App\Console\Commands;

use App\AccessControl;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

#[Signature('app:create-super-admin {email? : Correo de una cuenta existente que será promovida}')]
#[Description('Crea el primer superadministrador o asigna el rol a una cuenta existente')]
class CreateSuperAdminCommand extends Command
{
    public function handle(): int
    {
        app(RolePermissionSeeder::class)->run();
        $role = Role::findByName(AccessControl::SUPER_ADMIN_ROLE, 'web');
        $email = mb_strtolower(trim((string) ($this->argument('email') ?: $this->ask('Correo electrónico'))));
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            if (! $this->confirm("La cuenta {$user->email} recibirá acceso de superadministrador. ¿Continuar?")) {
                return self::FAILURE;
            }

            $user->assignRole($role);
            $this->components->info("Acceso de superadministrador asignado a {$user->email}.");

            return self::SUCCESS;
        }

        $name = trim((string) $this->ask('Nombre del superadministrador'));
        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres, mayúsculas, minúsculas y números)');
        $confirmation = (string) $this->secret('Confirma la contraseña');
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->assignRole($role);

        $this->components->info("Superadministrador {$user->email} creado.");

        return self::SUCCESS;
    }
}
