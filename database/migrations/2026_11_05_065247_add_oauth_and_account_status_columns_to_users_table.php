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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
            $table->string('account_status')->default('active')->index();
            $table->string('auth_method')->default('password');
            $table->string('google_id')->nullable()->unique();
            $table->timestamp('first_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
            $table->dropIndex(['account_status']);
            $table->dropColumn(['account_status', 'auth_method', 'google_id', 'first_login_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
