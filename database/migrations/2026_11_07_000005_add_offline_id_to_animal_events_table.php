<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_events', function (Blueprint $table): void {
            $table->uuid('offline_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('animal_events', fn (Blueprint $table) => $table->dropUnique(['offline_id'])->dropColumn('offline_id'));
    }
};
