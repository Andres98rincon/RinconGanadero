<?php

namespace Database\Seeders;

use App\Models\Breed;
use Illuminate\Database\Seeder;

class BreedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $breeds = [
            'bovine' => ['Brahman', 'Holstein', 'Jersey', 'Pardo suizo', 'Angus', 'Gyr', 'Girolando', 'Simmental'],
            'goat' => ['Alpina', 'Boer', 'Saanen', 'Nubia'],
            'sheep' => ['Dorper', 'Katahdin', 'Pelibuey'],
            'pig' => ['Landrace', 'Duroc', 'Yorkshire'],
            'horse' => ['Criollo', 'Cuarto de milla', 'Paso fino'],
            'buffalo' => ['Mediterraneo', 'Murrah'],
        ];

        foreach ($breeds as $species => $names) {
            foreach ($names as $name) {
                Breed::query()->firstOrCreate([
                    'species' => $species,
                    'name' => $name,
                ]);
            }
        }
    }
}
