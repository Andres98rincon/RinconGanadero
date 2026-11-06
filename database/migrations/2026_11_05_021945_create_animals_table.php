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
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->string('ear_tag', 40)->unique();
            $table->string('name', 100)->nullable();
            $table->string('species', 30)->index();
            $table->foreignId('breed_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sex', 20);
            $table->string('identification_method', 20)->default('visual');
            $table->string('microchip', 64)->nullable()->unique();
            $table->string('registration_number', 64)->nullable();
            $table->date('birth_date')->nullable()->index();
            $table->decimal('birth_weight_kg', 8, 2)->nullable();
            $table->string('color', 80)->nullable();
            $table->text('markings')->nullable();
            $table->string('purpose', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('reproductive_status', 30)->nullable();
            $table->foreignId('dam_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->foreignId('sire_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->string('acquisition_type', 20)->default('born');
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->string('supplier_name', 150)->nullable();
            $table->decimal('current_weight_kg', 8, 2)->nullable();
            $table->date('last_weighed_at')->nullable();
            $table->text('health_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['species', 'status']);
            $table->index(['sex', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};
