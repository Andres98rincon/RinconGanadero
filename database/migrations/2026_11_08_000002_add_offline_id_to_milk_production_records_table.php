<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milk_production_records', function (Blueprint $table): void {
            $table->uuid('offline_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('milk_production_records', function (Blueprint $table): void {
            $table->dropUnique(['offline_id']);
            $table->dropColumn('offline_id');
        });
    }
};
