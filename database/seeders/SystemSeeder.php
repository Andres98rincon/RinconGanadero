<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos base para dejar el sistema listo para trabajar (seguro también en producción):
 * roles y permisos, catálogo de razas y el superadministrador definido en el .env.
 * Se puede ejecutar varias veces sin duplicar información.
 *
 *   php artisan db:seed --class=SystemSeeder
 */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            BreedSeeder::class,
        ]);

        if (filled(config('services.super_admin.password'))) {
            $this->call(SuperAdminSeeder::class);
        } else {
            $this->command?->warn('No se creó el superadministrador: define SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL y SUPER_ADMIN_PASSWORD en el .env.');
        }
    }
}
