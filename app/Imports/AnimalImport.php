<?php

namespace App\Imports;

use App\AccessControl;
use App\Models\Animal;
use App\Models\Breed;
use App\Models\Farm;
use App\Models\Paddock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AnimalImport implements OnEachRow, WithChunkReading, WithHeadingRow
{
    public const HEADINGS = [
        'ear_tag',
        'name',
        'species',
        'breed_name',
        'sex',
        'identification_method',
        'microchip',
        'registration_number',
        'birth_date',
        'birth_weight_kg',
        'color',
        'markings',
        'purpose',
        'status',
        'reproductive_status',
        'farm_code',
        'paddock_code',
        'location',
        'dam_ear_tag',
        'sire_ear_tag',
        'acquisition_type',
        'acquisition_date',
        'acquisition_cost',
        'supplier_name',
        'current_weight_kg',
        'last_weighed_at',
        'health_notes',
        'notes',
    ];

    public const DISPLAY_HEADINGS = [
        'Identificador / arete',
        'Nombre del animal',
        'Especie',
        'Raza',
        'Sexo',
        'Tipo de identificación',
        'Microchip',
        'Número de registro',
        'Fecha de nacimiento',
        'Peso al nacer (kg)',
        'Color',
        'Marcas y señas',
        'Propósito',
        'Estado',
        'Estado reproductivo',
        'Código de finca',
        'Código de potrero',
        'Ubicación',
        'Identificador / arete de la madre',
        'Identificador / arete del padre',
        'Origen del animal',
        'Fecha de ingreso',
        'Costo de adquisición',
        'Nombre del proveedor',
        'Peso actual (kg)',
        'Fecha del último pesaje',
        'Observaciones de salud',
        'Observaciones adicionales',
    ];

    /**
     * @var list<array{row: int, errors: list<string>}>
     */
    private array $failures = [];

    private int $imported = 0;

    private int $failed = 0;

    /**
     * @var array<string, true>
     */
    private array $seenEarTags = [];

    public function __construct(private readonly User $actor) {}

    public function onRow(Row $row): void
    {
        $data = array_replace(
            array_fill_keys(self::HEADINGS, null),
            $this->normalizeHeadingKeys($row->toArray()),
        );

        if (count(array_filter($data, fn (mixed $value): bool => $value !== null && $value !== '')) === 0) {
            return;
        }

        $data = array_map(
            fn (mixed $value): mixed => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value,
            $data,
        );

        foreach (['birth_date', 'acquisition_date', 'last_weighed_at'] as $dateField) {
            $data[$dateField] = $this->normalizeDate($data[$dateField] ?? null);
        }

        $validator = Validator::make($data, [
            'ear_tag' => ['required', 'string', 'max:40', 'unique:animals,ear_tag'],
            'name' => ['nullable', 'string', 'max:100'],
            'species' => ['required', Rule::in(array_keys(\App\Models\Species::options()))],
            'breed_name' => ['nullable', 'string', 'max:100'],
            'sex' => ['required', Rule::in(['female', 'male', 'unknown'])],
            'identification_method' => ['required', Rule::in(['visual', 'rfid', 'tattoo', 'brand', 'other'])],
            'microchip' => ['nullable', 'string', 'max:64', 'unique:animals,microchip'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'birth_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'color' => ['nullable', 'string', 'max:80'],
            'markings' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['nullable', Rule::in(['dairy', 'meat', 'breeding', 'dual', 'work', 'other'])],
            'status' => ['required', Rule::in(array_keys(Animal::STATUSES))],
            'reproductive_status' => ['nullable', Rule::in(['open', 'served', 'pregnant', 'lactating', 'dry', 'not_applicable'])],
            'farm_code' => ['nullable', 'string', 'max:30'],
            'paddock_code' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:120'],
            'dam_ear_tag' => ['nullable', 'string', 'max:40'],
            'sire_ear_tag' => ['nullable', 'string', 'max:40'],
            'acquisition_type' => ['required', Rule::in(['born', 'purchased', 'donated', 'transferred', 'unknown'])],
            'acquisition_date' => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'current_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'last_weighed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'health_notes' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ], [
            'ear_tag.required' => 'Falta el identificador obligatorio (ear_tag).',
            'ear_tag.unique' => 'El identificador ya existe.',
            'species.in' => 'La especie debe usar un código válido, por ejemplo bovine.',
            'sex.in' => 'El sexo debe ser female, male o unknown.',
            'microchip.unique' => 'El microchip ya existe.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'acquisition_date.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'last_weighed_at.before_or_equal' => 'La fecha del pesaje no puede ser futura.',
        ]);

        $errors = $validator->errors()->all();
        $earTag = trim((string) ($data['ear_tag'] ?? ''));

        if ($earTag !== '' && isset($this->seenEarTags[$earTag])) {
            $errors[] = 'El identificador está duplicado dentro del archivo.';
        }

        $species = (string) ($data['species'] ?? '');
        $breed = $this->findBreed($data['breed_name'] ?? null, $species, $errors);
        $farm = $this->findFarm($data['farm_code'] ?? null, $errors);
        $paddock = $this->findPaddock($data['paddock_code'] ?? null, $farm, $errors);
        $dam = $this->findParent($data['dam_ear_tag'] ?? null, $earTag, $species, 'female', $errors);
        $sire = $this->findParent($data['sire_ear_tag'] ?? null, $earTag, $species, 'male', $errors);

        if ($errors !== []) {
            $this->addFailure($row->getIndex(), $errors);

            return;
        }

        $this->seenEarTags[$earTag] = true;

        DB::transaction(function () use ($data, $farm, $breed, $paddock, $dam, $sire): void {
            Animal::query()->create([
                'ear_tag' => trim((string) $data['ear_tag']),
                'name' => $this->nullableString($data['name'] ?? null),
                'species' => $data['species'],
                'breed_id' => $breed?->id,
                'farm_id' => $farm?->id,
                'paddock_id' => $paddock?->id,
                'sex' => $data['sex'],
                'identification_method' => $data['identification_method'],
                'microchip' => $this->nullableString($data['microchip'] ?? null),
                'registration_number' => $this->nullableString($data['registration_number'] ?? null),
                'birth_date' => $data['birth_date'] ?: null,
                'birth_weight_kg' => $data['birth_weight_kg'] ?: null,
                'color' => $this->nullableString($data['color'] ?? null),
                'markings' => $this->nullableString($data['markings'] ?? null),
                'purpose' => $this->nullableString($data['purpose'] ?? null),
                'status' => $data['status'],
                'reproductive_status' => $this->nullableString($data['reproductive_status'] ?? null),
                'location' => $this->nullableString($data['location'] ?? null),
                'dam_id' => $dam?->id,
                'sire_id' => $sire?->id,
                'acquisition_type' => $data['acquisition_type'],
                'acquisition_date' => $data['acquisition_date'] ?: null,
                'acquisition_cost' => $data['acquisition_cost'] ?: null,
                'supplier_name' => $this->nullableString($data['supplier_name'] ?? null),
                'current_weight_kg' => $data['current_weight_kg'] ?: null,
                'last_weighed_at' => $data['last_weighed_at'] ?: null,
                'health_notes' => $this->nullableString($data['health_notes'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'created_by' => $this->actor->id,
            ]);
        });

        $this->imported++;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeHeadingKeys(array $data): array
    {
        $localizedHeadings = array_combine(
            HeadingRowFormatter::format(self::DISPLAY_HEADINGS),
            self::HEADINGS,
        );
        $normalized = [];

        foreach ($data as $heading => $value) {
            $normalized[$localizedHeadings[$heading] ?? $heading] = $value;
        }

        return $normalized;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @return array{imported: int, failed: int, errors: list<array{row: int, errors: list<string>}>}
     */
    public function report(): array
    {
        return [
            'imported' => $this->imported,
            'failed' => $this->failed,
            'errors' => $this->failures,
        ];
    }

    /**
     * @param  list<string>  $errors
     */
    private function addFailure(int $row, array $errors): void
    {
        $this->failed++;

        if (count($this->failures) < 100) {
            $this->failures[] = [
                'row' => $row,
                'errors' => array_values(array_unique($errors)),
            ];
        }
    }

    private function normalizeDate(mixed $value): mixed
    {
        if (is_numeric($value) && (float) $value > 0 && (float) $value <= 2958465) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        return $value;
    }

    /**
     * @param  list<string>  $errors
     */
    private function findFarm(mixed $code, array &$errors): ?Farm
    {
        if (blank($code)) {
            if (! $this->actor->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
                $errors[] = 'Indica el código de una finca asignada en la columna farm_code.';
            }

            return null;
        }

        $farm = Farm::query()
            ->where('status', 'active')
            ->where('code', trim((string) $code))
            ->first();

        if (! $farm) {
            $errors[] = 'La finca indicada por farm_code no existe, está archivada o no está asignada a tu usuario.';
        }

        return $farm;
    }

    /**
     * @param  list<string>  $errors
     */
    private function findPaddock(mixed $code, ?Farm $farm, array &$errors): ?Paddock
    {
        if (blank($code)) {
            return null;
        }

        if (! $farm) {
            $errors[] = 'Especifica primero farm_code para asociar un potrero.';

            return null;
        }

        $paddock = Paddock::query()
            ->where('farm_id', $farm->id)
            ->where('code', trim((string) $code))
            ->first();

        if (! $paddock) {
            $errors[] = 'El potrero indicado no pertenece a la finca seleccionada.';
        }

        return $paddock;
    }

    /**
     * @param  list<string>  $errors
     */
    private function findBreed(mixed $name, string $species, array &$errors): ?Breed
    {
        if (blank($name)) {
            return null;
        }

        $breed = Breed::query()
            ->where('species', $species)
            ->where('name', trim((string) $name))
            ->first();

        if (! $breed) {
            $errors[] = 'La raza indicada no existe para la especie seleccionada.';
        }

        return $breed;
    }

    /**
     * @param  list<string>  $errors
     */
    private function findParent(mixed $earTag, string $currentEarTag, string $species, string $sex, array &$errors): ?Animal
    {
        if (blank($earTag)) {
            return null;
        }

        $parent = Animal::query()->where('ear_tag', trim((string) $earTag))->first();

        if (! $parent || $parent->sex !== $sex || $parent->species !== $species || $parent->ear_tag === $currentEarTag) {
            $errors[] = $sex === 'female'
                ? 'La madre debe existir, ser hembra y pertenecer a la misma especie.'
                : 'El padre debe existir, ser macho y pertenecer a la misma especie.';
        }

        return $parent;
    }

    private function nullableString(mixed $value): ?string
    {
        return blank($value) ? null : trim((string) $value);
    }
}
