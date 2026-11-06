<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('milk_production_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->date('record_date');
            $table->decimal('morning_liters', 8, 2)->nullable();
            $table->decimal('evening_liters', 8, 2)->nullable();
            $table->decimal('total_liters', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['animal_id', 'record_date']);
            $table->index(['record_date', 'animal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_production_records');
    }
};
