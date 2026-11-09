<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\Species;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Especies base. Idempotente: solo inserta las que faltan (no sobrescribe nombres editados
 * desde el CRUD) y enlaza las razas existentes con su especie.
 */
class SpeciesSeeder extends Seeder
{
    /** @var array<string, string> */
    public const DESCRIPTIONS = [
        'bovine' => 'Ganado vacuno de leche, carne o doble propósito.',
        'goat' => 'Cabras para leche, carne o cría.',
        'sheep' => 'Ovejas y ovinos de pelo o lana.',
        'pig' => 'Cerdos de cría, levante y ceba.',
        'horse' => 'Caballos, yeguas y mulares de trabajo o silla.',
        'buffalo' => 'Búfalos de agua para leche y carne.',
        'other' => 'Otras especies del predio.',
    ];

    public function run(): void
    {
        $now = now();
        $species = collect(Animal::SPECIES)->map(fn (string $name, string $code): array => [
            'code' => $code, 'name' => $name, 'description' => self::DESCRIPTIONS[$code] ?? null, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Códigos que ya usan animales o razas aunque no estén en la lista base.
        $usedCodes = collect()
            ->merge(Schema::hasTable('breeds') ? DB::table('breeds')->distinct()->pluck('species') : [])
            ->merge(Schema::hasTable('animals') ? DB::table('animals')->distinct()->pluck('species') : [])
            ->filter()->unique()->reject(fn (string $code): bool => $species->has($code));

        foreach ($usedCodes as $code) {
            $species->put($code, ['code' => $code, 'name' => Str::headline($code), 'description' => null, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('species')->insertOrIgnore($species->values()->all());

        foreach (DB::table('species')->pluck('id', 'code') as $code => $id) {
            DB::table('breeds')->where('species', $code)->whereNull('species_id')->update(['species_id' => $id]);
        }

        Cache::forget(Species::CACHE_KEY);
    }
}
