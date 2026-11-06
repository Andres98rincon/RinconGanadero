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
        Schema::table('animals', function (Blueprint $table) {
            $table->date('expected_calving_date')->nullable()->after('reproductive_status');
            $table->index(['reproductive_status', 'expected_calving_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropIndex(['reproductive_status', 'expected_calving_date']);
            $table->dropColumn('expected_calving_date');
        });
    }
};
