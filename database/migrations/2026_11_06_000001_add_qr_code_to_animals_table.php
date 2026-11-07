<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table): void {
            $table->string('qr_code', 16)->nullable()->unique();
        });

        DB::table('animals')->whereNull('qr_code')->orderBy('id')->chunkById(500, function ($animals): void {
            foreach ($animals as $animal) {
                DB::table('animals')->where('id', $animal->id)->update([
                    'qr_code' => Str::upper(Str::random(16)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table): void {
            $table->dropUnique(['qr_code']);
            $table->dropColumn('qr_code');
        });
    }
};
