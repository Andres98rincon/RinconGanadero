<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos base para dejar el sistema listo para trabajar (seguro también en producción):
 * roles y permisos, especies, razas, departamentos y municipios de Colombia y el
 * superadministrador definido en el .env.
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
            SpeciesSeeder::class,
            BreedSeeder::class,
            ColombiaLocationsSeeder::class,
        ]);

        if (filled(config('services.super_admin.password'))) {
            $this->call(SuperAdminSeeder::class);
        } else {
            $this->command?->warn('No se creó el superadministrador: define SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL y SUPER_ADMIN_PASSWORD en el .env.');
        }
    }
}
