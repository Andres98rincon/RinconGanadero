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
        Schema::create('animal_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_type', 30);
            $table->date('movement_date');
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('animal_ear_tag', 40);
            $table->foreignId('from_farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->foreignId('to_farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('from_farm_name', 150)->nullable();
            $table->string('to_farm_name', 150)->nullable();
            $table->foreignId('from_paddock_id')->nullable()->constrained('paddocks')->nullOnDelete();
            $table->foreignId('to_paddock_id')->nullable()->constrained('paddocks')->nullOnDelete();
            $table->string('from_location', 120)->nullable();
            $table->string('to_location', 120)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('exit_reason', 40)->nullable();
            $table->string('counterparty_name', 150)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['movement_type', 'movement_date']);
            $table->index(['animal_id', 'movement_date']);
            $table->index(['from_farm_id', 'movement_date']);
            $table->index(['to_farm_id', 'movement_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animal_movements');
    }
};
