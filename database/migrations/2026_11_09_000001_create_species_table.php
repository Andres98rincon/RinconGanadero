<?php

use Database\Seeders\SpeciesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('breeds', function (Blueprint $table): void {
            $table->foreignId('species_id')->nullable()->after('id')->constrained('species')->restrictOnDelete();
        });

        // Las especies base deben existir desde la instalación, también en producción.
        (new SpeciesSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('breeds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('species_id');
        });

        Schema::dropIfExists('species');
    }
};
