<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reminder_key', 191);
            $table->uuid('notification_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['user_id', 'reminder_key']);
        });
    }

    public function down(): void { Schema::dropIfExists('notification_deliveries'); }
};
