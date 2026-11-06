<?php

namespace Tests\Feature;

use App\AccessControl;
use App\Exports\AnimalExport;
use App\Exports\AnimalTemplateExport;
use App\Imports\AnimalImport;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\Paddock;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnimalImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_download_the_import_template_and_exports(): void
    {
        $administrator = $this->administrator();
        $farm = Farm::factory()->create();
        $administrator->farms()->attach($farm);
        $animal = Animal::factory()->create(['farm_id' => $farm->id]);
        Excel::fake();
        $this->travelTo('2026-11-06 12:00:00');
        $this->actingAs($administrator);

        $this->get(route('animals.import-template'))->assertOk();
        Excel::assertDownloaded(
            'plantilla-animales.xlsx',
            fn (AnimalTemplateExport $export): bool => $export->headings() === AnimalImport::DISPLAY_HEADINGS,
        );

        $this->get(route('animals.export', ['format' => 'csv', 'farm_id' => $farm->id]))->assertOk();
        Excel::assertDownloaded('animales-'.now()->format('Ymd-His').'.csv', function (AnimalExport $export) use ($animal): bool {
            return $export->headings() === AnimalImport::DISPLAY_HEADINGS
                && $export->query()->pluck('id')->all() === [$animal->id];
        });
    }

    public function test_animal_import_rejects_duplicate_rows_and_preserves_farm_and_paddock_relations(): void
    {
        $administrator = $this->administrator();
        $farm = Farm::factory()->create(['code' => 'IMPORT-FARM']);
        $administrator->farms()->attach($farm);
        $paddock = Paddock::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'IMPORT-PADDOCK',
        ]);
        $file = UploadedFile::fake()->createWithContent('animals.csv', implode("\n", [
            'Identificador / arete,Nombre del animal,Especie,Sexo,Tipo de identificación,Estado,Origen del animal,Código de finca,Código de potrero',
            'IMPORT-001,Lucero,bovine,female,visual,active,born,IMPORT-FARM,IMPORT-PADDOCK',
            'IMPORT-001,Duplicado,bovine,female,visual,active,born,IMPORT-FARM,IMPORT-PADDOCK',
        ]));

        $response = $this->actingAs($administrator)->post(route('animals.import'), ['file' => $file]);

        $response->assertRedirect(route('animals.index'))
            ->assertSessionHas('import_report', fn (array $report): bool => $report['imported'] === 1 && $report['failed'] === 1);
        $this->assertDatabaseHas('animals', [
            'ear_tag' => 'IMPORT-001',
            'farm_id' => $farm->id,
            'paddock_id' => $paddock->id,
            'created_by' => $administrator->id,
        ]);
    }

    public function test_operator_cannot_import_animals(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole(Role::findByName(AccessControl::OPERATOR_ROLE, 'web'));

        $this->actingAs($operator)
            ->post(route('animals.import'), ['file' => UploadedFile::fake()->create('animals.csv', 1, 'text/csv')])
            ->assertForbidden();
    }

    private function administrator(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName(AccessControl::ADMIN_ROLE, 'web'));

        return $administrator;
    }
}
