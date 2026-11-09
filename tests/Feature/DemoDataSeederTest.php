<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Breed;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_seeder_loads_the_breed_catalog_without_duplicates(): void
    {
        $this->seed(SystemSeeder::class);
        $this->seed(SystemSeeder::class);

        $this->assertSame(1, Breed::query()->where('species', 'bovine')->where('name', 'Holstein')->count());
        $this->assertTrue(Breed::query()->where('name', 'Blanco Orejinegro (BON)')->exists());
    }

    public function test_demo_seeder_fills_every_module_with_related_data(): void
    {
        Storage::fake('public');

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(3, Farm::query()->where('code', 'like', 'DEMO-%')->count());
        $this->assertGreaterThan(80, Animal::query()->count());
        $this->assertGreaterThan(500, MilkProductionRecord::query()->count());
        $this->assertTrue(Animal::query()->where('reproductive_status', 'pregnant')->exists());
        $this->assertTrue(Animal::query()->whereNotNull('dam_id')->exists());
        $this->assertTrue(Animal::query()->where('status', 'sold')->exists());
        $this->assertDatabaseHas('users', ['email' => 'operador@hatosoft.test']);
        $this->assertSame(1, User::query()->where('email', 'operador@hatosoft.test')->first()->farms()->count());
        $this->assertGreaterThan(0, DB::table('financial_transactions')->where('category', 'milk_sales')->count());

        $this->seed(DemoDataSeeder::class);
        $this->assertSame(3, Farm::query()->where('code', 'like', 'DEMO-%')->count());
    }
}
