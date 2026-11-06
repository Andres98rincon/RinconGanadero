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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('category', 50);
            $table->string('title', 150);
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'transaction_date']);
            $table->index(['farm_id', 'transaction_date']);
            $table->index(['animal_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
