<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed                          → datos base (roles, permisos, razas, superadmin)
     * php artisan db:seed --class=DemoDataSeeder   → además, fincas y animales de prueba
     */
    public function run(): void
    {
        $this->call(SystemSeeder::class);
    }
}
