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
        Schema::create('paddocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->decimal('area_hectares', 10, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_id', 'code']);
            $table->index(['farm_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paddocks');
    }
};
