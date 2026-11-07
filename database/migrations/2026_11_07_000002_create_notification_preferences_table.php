<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedTinyInteger('lead_days')->default(1);
            $table->json('categories')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void { Schema::dropIfExists('notification_preferences'); }
};
