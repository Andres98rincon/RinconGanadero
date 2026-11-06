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
        Schema::create('animal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30)->index();
            $table->date('event_date')->index();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['animal_id', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animal_events');
    }
};
