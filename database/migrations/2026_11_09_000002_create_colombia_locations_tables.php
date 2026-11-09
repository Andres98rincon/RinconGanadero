<?php

use Database\Seeders\ColombiaLocationsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('code', 5)->unique();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });

        // Departamentos y municipios disponibles desde la instalación, también en producción.
        (new ColombiaLocationsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('departments');
    }
};
