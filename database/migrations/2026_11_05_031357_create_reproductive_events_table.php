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
        Schema::create('reproductive_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->date('event_date');
            $table->foreignId('sire_animal_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->string('sire_name', 150)->nullable();
            $table->string('semen_code', 100)->nullable();
            $table->string('technician', 150)->nullable();
            $table->string('diagnosis_result', 20)->nullable();
            $table->date('expected_calving_date')->nullable();
            $table->string('calving_difficulty', 20)->nullable();
            $table->unsignedTinyInteger('gestation_weeks')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['animal_id', 'event_date']);
            $table->index(['event_type', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reproductive_events');
    }
};
