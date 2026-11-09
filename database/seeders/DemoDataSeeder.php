<?php

namespace Database\Seeders;

use App\AccessControl;
use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\AnimalValuation;
use App\Models\Breed;
use App\Models\CalendarReminder;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\NotificationPreference;
use App\Models\Paddock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\ActivityLogStatus;
use Throwable;

/**
 * Datos de prueba realistas para revisar todos los módulos: 3 fincas colombianas, potreros,
 * usuarios de prueba, ~100 animales con genealogía, reproducción, leche diaria con curva de
 * lactancia, pesajes, sanidad, finanzas, compras/ventas/traslados, valoraciones, recordatorios y fotos.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * No se ejecuta en producción y no se duplica si los datos de prueba ya existen.
 */
class DemoDataSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'HatoDemo2026';

    /** @var array<string, array<string, mixed>> */
    private const FARMS = [
        'DEMO-ESP' => [
            'name' => 'Hacienda La Esperanza', 'municipality' => 'Montería', 'province' => 'Córdoba', 'address' => 'Vereda Los Garzones, km 12 vía Cereté',
            'latitude' => 8.7479, 'longitude' => -75.8814, 'area_hectares' => 180, 'manager_name' => 'Rubén Darío Díaz', 'phone' => '3104567890',
            'prefix' => 'ESP', 'profile' => 'dual', 'milk_price' => 1750, 'cows' => 16, 'heifers' => 5, 'steers' => 0,
            'cow_breeds' => ['Girolando', 'Girolando', 'Gyr', 'Costeño con Cuernos', 'Romosinuano', 'Brahman'], 'bull_breeds' => ['Gyr', 'Brahman'],
            'paddocks' => [['P-01', 'La Ceiba', 22, 25], ['P-02', 'El Guadual', 18, 20], ['P-03', 'Las Palmas', 25, 30], ['P-04', 'Maternidad', 6, 10], ['P-05', 'Terneros', 4, 15]],
            'notes' => 'Doble propósito: ordeño una vez al día con ternero al pie.',
        ],
        'DEMO-POR' => [
            'name' => 'Finca El Porvenir', 'municipality' => 'San Pedro de los Milagros', 'province' => 'Antioquia', 'address' => 'Vereda La Lana, sector El Alto',
            'latitude' => 6.4597, 'longitude' => -75.5566, 'area_hectares' => 65, 'manager_name' => 'Marta Lucía Ríos', 'phone' => '3127894561',
            'prefix' => 'POR', 'profile' => 'dairy', 'milk_price' => 2050, 'cows' => 18, 'heifers' => 6, 'steers' => 0,
            'cow_breeds' => ['Holstein', 'Holstein', 'Holstein', 'Jersey', 'Normando', 'Pardo suizo', 'Ayrshire'], 'bull_breeds' => ['Holstein'],
            'paddocks' => [['K-01', 'Kikuyo 1', 4.5, 18], ['K-02', 'Kikuyo 2', 4, 16], ['K-03', 'Ordeño', 3, 20], ['K-04', 'Novillas', 5, 12], ['K-05', 'Terneras', 1.5, 10]],
            'notes' => 'Lechería especializada de trópico alto, dos ordeños diarios con equipo mecánico.',
        ],
        'DEMO-SRO' => [
            'name' => 'Hato Santa Rosa', 'municipality' => 'Puerto López', 'province' => 'Meta', 'address' => 'Vereda Remolinos, finca Santa Rosa',
            'latitude' => 4.0890, 'longitude' => -72.9600, 'area_hectares' => 420, 'manager_name' => 'Jairo Alberto Castro', 'phone' => '3209871234',
            'prefix' => 'SRO', 'profile' => 'beef', 'milk_price' => 0, 'cows' => 14, 'heifers' => 4, 'steers' => 10,
            'cow_breeds' => ['Brahman', 'Brahman', 'Nelore', 'Cebú comercial', 'Sanmartinero', 'Brangus'], 'bull_breeds' => ['Brahman', 'Brangus'],
            'paddocks' => [['S-01', 'Sabana Grande', 120, 60], ['S-02', 'Morichal', 80, 40], ['S-03', 'Brachiaria Norte', 60, 35], ['S-04', 'Brachiaria Sur', 55, 35], ['S-05', 'Corral de manejo', 2, 80]],
            'notes' => 'Cría y levante de ganado de carne en sabana.',
        ],
    ];

    private const COW_NAMES = ['Lucero', 'Paloma', 'Canela', 'Mariposa', 'Estrella', 'Princesa', 'Margarita', 'Violeta', 'Golondrina', 'Azucena', 'Morena', 'Chispa', 'Gaviota', 'Rosita', 'Tormenta', 'Luna', 'Brisa', 'Perla', 'Dulce', 'Pinta', 'Florecita', 'Campana', 'Bonita', 'Gacela', 'Muñeca', 'Aurora', 'Primavera', 'Manzana', 'Frijolita', 'Galleta', 'Pecosa', 'Duquesa', 'Reina', 'Sirena', 'Panela', 'Jacinta', 'Lola', 'Candelaria', 'Esmeralda', 'Nube', 'Cereza', 'Mora', 'Tulipa', 'Coqueta', 'Gitana', 'Bella', 'Linda', 'Pradera'];

    private const BULL_NAMES = ['Tornado', 'Sultán', 'Capitán', 'Relámpago', 'Titán', 'Huracán', 'Lucero Real', 'Emperador', 'Bravo', 'Centauro', 'Faraón', 'Trueno', 'Coronel', 'Atlas'];

    /** Litros/día en el pico de lactancia por raza. */
    private const MILK_PEAK = ['Holstein' => 26, 'Jersey' => 17, 'Pardo suizo' => 19, 'Normando' => 17, 'Ayrshire' => 18, 'Girolando' => 13, 'Gyr' => 8, 'Costeño con Cuernos' => 6, 'Romosinuano' => 4.5];

    /** Peso adulto de una hembra por raza (kg). */
    private const ADULT_WEIGHT = ['Holstein' => 600, 'Jersey' => 410, 'Pardo suizo' => 580, 'Normando' => 560, 'Ayrshire' => 520, 'Girolando' => 500, 'Gyr' => 450, 'Costeño con Cuernos' => 400, 'Romosinuano' => 470, 'Brahman' => 480, 'Nelore' => 470, 'Cebú comercial' => 450, 'Sanmartinero' => 430, 'Brangus' => 520];

    private const COLORS = ['Holstein' => 'Blanco y negro', 'Jersey' => 'Bayo', 'Pardo suizo' => 'Pardo', 'Normando' => 'Barcino', 'Ayrshire' => 'Rojo y blanco', 'Girolando' => 'Negro jaspeado', 'Gyr' => 'Rojo moteado', 'Costeño con Cuernos' => 'Bayo claro', 'Romosinuano' => 'Rojo', 'Brahman' => 'Blanco grisáceo', 'Nelore' => 'Blanco', 'Cebú comercial' => 'Gris', 'Sanmartinero' => 'Colorado', 'Brangus' => 'Negro'];

    private CarbonImmutable $today;

    /** @var array<string, int> */
    private array $breedIds = [];

    /** @var array<string, int> */
    private array $tagCounters = [];

    private int $femaleNameIndex = 0;

    private int $maleNameIndex = 0;

    private ?User $admin = null;

    /** @var array<int, array<string, mixed>> farm id => resumen de leche/eventos para finanzas */
    private array $farmStats = [];

    /** @var list<array{animal: Animal, image: string}> */
    private array $photoQueue = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoDataSeeder no se ejecuta en producción.');

            return;
        }

        if (Farm::query()->withoutGlobalScopes()->withTrashed()->whereIn('code', array_keys(self::FARMS))->exists()) {
            $this->command?->warn('Los datos de prueba ya están cargados (existen las fincas DEMO-*). No se duplicó nada.');

            return;
        }

        $this->call(SystemSeeder::class);

        mt_srand(2026);
        $this->today = CarbonImmutable::today();
        $this->breedIds = Breed::query()->where('species', 'bovine')->pluck('id', 'name')->all();
        app(ActivityLogStatus::class)->disable();

        try {
            DB::transaction(function (): void {
                $users = $this->seedUsers();
                $this->admin = $users['admin'];

                foreach (self::FARMS as $code => $config) {
                    $this->seedFarm($code, $config);
                }

                $this->assignFarms($users);
                $this->seedTransfers();
            });

            $this->attachPhotos();
        } finally {
            app(ActivityLogStatus::class)->enable();
        }

        $this->printSummary();
    }

    /* ------------------------------------------------------------------ Users */

    /**
     * @return array{admin: User, operator: User}
     */
    private function seedUsers(): array
    {
        $admin = User::query()->firstOrCreate(['email' => 'admin@hatosoft.test'], ['name' => 'Laura Gómez', 'password' => self::DEMO_PASSWORD]);
        $admin->forceFill(['account_status' => 'active', 'auth_method' => 'password', 'email_verified_at' => now()])->save();
        $admin->syncRoles([AccessControl::ADMIN_ROLE]);

        $operator = User::query()->firstOrCreate(['email' => 'operador@hatosoft.test'], ['name' => 'Carlos Pérez', 'password' => self::DEMO_PASSWORD]);
        $operator->forceFill(['account_status' => 'active', 'auth_method' => 'password', 'email_verified_at' => now()])->save();
        $operator->syncRoles([AccessControl::OPERATOR_ROLE]);

        foreach ([$admin, $operator] as $user) {
            NotificationPreference::query()->updateOrCreate(['user_id' => $user->id], ['enabled' => true, 'lead_days' => 2]);
        }

        return ['admin' => $admin, 'operator' => $operator];
    }

    /**
     * @param  array{admin: User, operator: User}  $users
     */
    private function assignFarms(array $users): void
    {
        $farms = Farm::query()->withoutGlobalScopes()->whereIn('code', array_keys(self::FARMS))->pluck('id', 'code');
        $users['admin']->farms()->syncWithoutDetaching($farms->values()->all());
        $users['operator']->farms()->syncWithoutDetaching([$farms['DEMO-POR']]);
    }

    /* ------------------------------------------------------------------ Farms */

    /**
     * @param  array<string, mixed>  $config
     */
    private function seedFarm(string $code, array $config): void
    {
        $farm = Farm::query()->create([
            'code' => $code,
            'name' => $config['name'],
            'address' => $config['address'],
            'municipality' => $config['municipality'],
            'province' => $config['province'],
            'latitude' => $config['latitude'],
            'longitude' => $config['longitude'],
            'area_hectares' => $config['area_hectares'],
            'manager_name' => $config['manager_name'],
            'phone' => $config['phone'],
            'email' => strtolower($config['prefix']).'@hatosoft.test',
            'status' => 'active',
            'notes' => $config['notes'],
        ]);
        $this->farmStats[$farm->id] = ['config' => $config, 'inseminations' => [], 'vaccinated' => 0];

        $paddocks = collect($config['paddocks'])->map(fn (array $paddock): Paddock => Paddock::query()->create([
            'farm_id' => $farm->id, 'code' => $paddock[0], 'name' => $paddock[1], 'area_hectares' => $paddock[2], 'capacity' => $paddock[3], 'status' => 'active',
        ]))->values();

        $bulls = collect($config['bull_breeds'])->map(fn (string $breed): Animal => $this->createAnimal($farm, $paddocks[2], [
            'sex' => 'male', 'breed' => $breed, 'birth_date' => $this->today->subMonths(mt_rand(36, 72)), 'purpose' => 'breeding',
            'registration_number' => 'ASO-'.mt_rand(100000, 999999),
        ]));

        $cows = collect(range(1, $config['cows']))->map(function () use ($farm, $paddocks, $config): Animal {
            $purchased = mt_rand(1, 100) <= 20;

            return $this->createAnimal($farm, $paddocks[mt_rand(0, 2)], [
                'sex' => 'female',
                'breed' => $config['cow_breeds'][array_rand($config['cow_breeds'])],
                'birth_date' => $this->today->subMonths(mt_rand(34, 110)),
                'purpose' => ['dairy' => 'dairy', 'dual' => 'dual', 'beef' => 'meat'][$config['profile']],
                'acquisition_type' => $purchased ? 'purchased' : 'born',
                'acquisition_date' => $purchased ? $this->today->subMonths(mt_rand(10, 30)) : null,
                'acquisition_cost' => $purchased ? mt_rand(28, 55) * 100000 : null,
                'supplier_name' => $purchased ? ['Subasta Ganadera de Córdoba', 'Hacienda Las Brisas', 'Ganadería El Recreo', 'Feria de Medellín'][mt_rand(0, 3)] : null,
            ]);
        });

        $heifers = collect(range(1, $config['heifers']))->map(fn (): Animal => $this->createAnimal($farm, $paddocks[3], [
            'sex' => 'female',
            'breed' => $config['cow_breeds'][array_rand($config['cow_breeds'])],
            'birth_date' => $this->today->subMonths(mt_rand(13, 26)),
            'purpose' => $config['profile'] === 'beef' ? 'breeding' : 'dairy',
            'dam_id' => $cows->random()->id,
            'sire_id' => $bulls->random()->id,
        ]));

        $steers = collect(range(1, $config['steers']))->map(fn (): Animal => $this->createAnimal($farm, $paddocks[mt_rand(2, 3)], [
            'sex' => 'male',
            'breed' => $config['cow_breeds'][array_rand($config['cow_breeds'])],
            'birth_date' => $this->today->subMonths(mt_rand(14, 28)),
            'purpose' => 'meat',
        ]));

        $calves = collect();
        $lactating = collect();

        foreach ($cows as $cow) {
            $result = $this->seedReproduction($farm, $cow, $bulls, $paddocks[4]);
            if ($result['calf']) {
                $calves->push($result['calf']);
            }
            if ($result['calved_at'] && $config['profile'] !== 'beef' && isset(self::MILK_PEAK[$this->breedName($cow)])) {
                $lactating->push(['cow' => $cow, 'calved_at' => $result['calved_at']]);
            }
        }

        foreach ($heifers->take(2) as $heifer) {
            $heifer->reproductiveEvents()->create(['event_type' => 'heat', 'event_date' => $this->today->subDays(mt_rand(2, 18))->toDateString(), 'notes' => 'Primer celo observado.']);
            $heifer->syncReproductiveStatus();
        }

        $this->seedMilk($farm, $lactating);
        $allAnimals = $bulls->concat($cows)->concat($heifers)->concat($steers)->concat($calves);
        $this->seedWeights($allAnimals);
        $this->seedHealth($farm, $allAnimals, $calves);
        $this->seedValuations($cows->concat($bulls)->concat($steers));
        $this->seedMovements($farm, $config, $steers, $paddocks);
        $this->seedReminders($farm, $paddocks);
        $this->seedFinances($farm, $config, $allAnimals->count());

        $photos = ['Girolando' => 'girolando-5-8.jpg', 'Gyr' => 'girolando-half-blood.jpg', 'Holstein' => 'girolando-half-blood.jpg'];
        $cows->filter(fn (Animal $cow): bool => isset($photos[$this->breedName($cow)]))->take(3)
            ->each(fn (Animal $cow) => $this->photoQueue[] = ['animal' => $cow, 'image' => $photos[$this->breedName($cow)]]);
        $calves->take(2)->each(fn (Animal $calf) => $this->photoQueue[] = ['animal' => $calf, 'image' => 'domino.jpeg']);
    }

    /* ---------------------------------------------------------------- Animals */

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAnimal(Farm $farm, Paddock $paddock, array $attributes): Animal
    {
        $prefix = self::FARMS[$farm->code]['prefix'];
        $this->tagCounters[$prefix] = ($this->tagCounters[$prefix] ?? 0) + 1;
        $breed = $attributes['breed'];
        unset($attributes['breed']);
        $isFemale = $attributes['sex'] === 'female';
        $birthDate = CarbonImmutable::parse($attributes['birth_date']);
        $hasName = $isFemale ? $birthDate->lessThan($this->today->subMonths(12)) : ($attributes['purpose'] ?? null) === 'breeding';

        return Animal::query()->create(array_merge([
            'ear_tag' => sprintf('%s-%04d', $prefix, $this->tagCounters[$prefix]),
            'name' => $hasName ? ($isFemale ? $this->nextName(self::COW_NAMES, $this->femaleNameIndex) : $this->nextName(self::BULL_NAMES, $this->maleNameIndex)) : null,
            'species' => 'bovine',
            'breed_id' => $this->breedIds[$breed] ?? null,
            'farm_id' => $farm->id,
            'paddock_id' => $paddock->id,
            'identification_method' => ['brand', 'rfid', 'visual'][mt_rand(0, 2)],
            'microchip' => mt_rand(1, 100) <= 35 ? '9820000'.mt_rand(10000000, 99999999) : null,
            'birth_weight_kg' => round(mt_rand(280, 400) / 10, 1),
            'color' => self::COLORS[$breed] ?? 'Mixto',
            'status' => 'active',
            'acquisition_type' => 'born',
            'location' => $paddock->name,
            'created_by' => $this->admin?->id,
        ], $attributes, ['birth_date' => $birthDate->toDateString()]));
    }

    /**
     * Crea el historial reproductivo de una vaca y devuelve la cría y la fecha del último parto.
     *
     * @param  \Illuminate\Support\Collection<int, Animal>  $bulls
     * @return array{calf: ?Animal, calved_at: ?CarbonImmutable}
     */
    private function seedReproduction(Farm $farm, Animal $cow, $bulls, Paddock $calfPaddock): array
    {
        $useInsemination = self::FARMS[$farm->code]['profile'] === 'dairy' || mt_rand(1, 100) <= 30;
        $roll = mt_rand(1, 100);
        $calf = null;
        $calvedAt = null;

        $service = function (CarbonImmutable $date) use ($cow, $bulls, $useInsemination, $farm): void {
            $bull = $bulls->random();
            $cow->reproductiveEvents()->create($useInsemination ? [
                'event_type' => 'artificial_insemination', 'event_date' => $date->toDateString(),
                'sire_name' => $this->breedName($bull).' — pajilla importada', 'semen_code' => 'PJ-'.mt_rand(1000, 9999), 'technician' => 'Juan Carlos Mejía (inseminador)',
            ] : [
                'event_type' => 'natural_mating', 'event_date' => $date->toDateString(), 'sire_animal_id' => $bull->id, 'sire_name' => $bull->name,
            ]);
            if ($useInsemination) {
                $this->farmStats[$farm->id]['inseminations'][] = $date;
            }
        };
        $diagnose = function (CarbonImmutable $serviceDate) use ($cow): void {
            $diagnosisDate = $serviceDate->addDays(mt_rand(38, 50));
            if ($diagnosisDate->lessThanOrEqualTo($this->today)) {
                $cow->reproductiveEvents()->create([
                    'event_type' => 'pregnancy_diagnosis', 'event_date' => $diagnosisDate->toDateString(), 'diagnosis_result' => 'pregnant',
                    'expected_calving_date' => $serviceDate->addDays(283)->toDateString(), 'technician' => 'MV Andrea Salcedo', 'notes' => 'Palpación rectal positiva.',
                ]);
            }
        };

        if ($roll <= 45) {
            $calvedAt = $this->today->subDays(mt_rand(15, 240));
            $previousService = $calvedAt->subDays(283);
            $service($previousService);
            $diagnose($previousService);
            $calvingEvent = $cow->reproductiveEvents()->create([
                'event_type' => 'calving', 'event_date' => $calvedAt->toDateString(), 'calving_difficulty' => mt_rand(1, 100) <= 85 ? 'normal' : 'assisted',
                'notes' => 'Parto en potrero de maternidad.',
            ]);
            $calf = $this->createAnimal($farm, $calfPaddock, [
                'sex' => mt_rand(0, 1) ? 'female' : 'male', 'breed' => $this->breedName($cow), 'birth_date' => $calvedAt,
                'purpose' => $cow->purpose, 'dam_id' => $cow->id, 'sire_id' => $useInsemination ? null : $bulls->random()->id,
                'identification_method' => 'visual',
            ]);
            $calvingEvent->calves()->attach($calf->id);

            if ($calvedAt->lessThan($this->today->subDays(110))) {
                $newService = $calvedAt->addDays(mt_rand(65, 95));
                $service($newService);
                $diagnose($newService);
            }
        } elseif ($roll <= 72) {
            $serviceDate = $this->today->subDays(mt_rand(45, 260));
            $service($serviceDate);
            $diagnose($serviceDate);
        } elseif ($roll <= 87) {
            $service($this->today->subDays(mt_rand(4, 30)));
        } else {
            $cow->reproductiveEvents()->create(['event_type' => 'heat', 'event_date' => $this->today->subDays(mt_rand(2, 20))->toDateString(), 'notes' => 'Celo observado en el ordeño de la mañana.']);
        }

        $cow->syncReproductiveStatus();

        return ['calf' => $calf, 'calved_at' => $calvedAt];
    }

    /* ------------------------------------------------------------------- Milk */

    /**
     * @param  \Illuminate\Support\Collection<int, array{cow: Animal, calved_at: CarbonImmutable}>  $lactating
     */
    private function seedMilk(Farm $farm, $lactating): void
    {
        $twoMilkings = self::FARMS[$farm->code]['profile'] === 'dairy';
        $rows = [];
        $now = now();

        foreach ($lactating as ['cow' => $cow, 'calved_at' => $calvedAt]) {
            $peak = self::MILK_PEAK[$this->breedName($cow)] * (mt_rand(85, 115) / 100);
            $start = $calvedAt->addDays(4)->max($this->today->subDays(119));

            for ($date = $start; $date->lessThanOrEqualTo($this->today->subDay()); $date = $date->addDay()) {
                if (mt_rand(1, 100) <= 3) {
                    continue;
                }

                $daysInMilk = $calvedAt->diffInDays($date);
                $curve = $daysInMilk <= 50 ? 0.72 + 0.28 * ($daysInMilk / 50) : max(0.35, 1 - ($daysInMilk - 50) * 0.0024);
                $total = max(1, $peak * $curve * (mt_rand(92, 108) / 100));
                $morning = round($twoMilkings ? $total * 0.57 : $total, 1);
                $evening = $twoMilkings ? round($total - $morning, 1) : null;

                $rows[] = [
                    'animal_id' => $cow->id, 'record_date' => $date->toDateString(), 'morning_liters' => $morning, 'evening_liters' => $evening,
                    'total_liters' => round($morning + ($evening ?? 0), 2), 'notes' => null, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('milk_production_records')->insert($chunk);
        }
    }

    /* ---------------------------------------------------------------- Weights */

    /**
     * @param  \Illuminate\Support\Collection<int, Animal>  $animals
     */
    private function seedWeights($animals): void
    {
        foreach ($animals as $animal) {
            $breed = $this->breedName($animal);
            $adult = (self::ADULT_WEIGHT[$breed] ?? 470) * ($animal->sex === 'male' ? 1.5 : 1) * (mt_rand(92, 108) / 100);
            $birthDate = CarbonImmutable::parse($animal->birth_date);
            $ageDays = $birthDate->diffInDays($this->today);
            $isYoung = $ageDays < 900;
            $dates = $isYoung
                ? collect(range(5, 0))->map(fn (int $months) => $this->today->subMonths($months)->subDays(mt_rand(0, 6)))
                : collect([$this->today->subDays(mt_rand(150, 175)), $this->today->subDays(mt_rand(60, 95))]);

            foreach ($dates as $date) {
                if ($date->lessThanOrEqualTo($birthDate->addDays(20))) {
                    continue;
                }

                $days = $birthDate->diffInDays($date);
                $growth = ($animal->purpose === 'meat' || $animal->sex === 'male') ? 0.62 : 0.55;
                $weight = min($adult, (float) $animal->birth_weight_kg + $days * $growth) * (mt_rand(97, 103) / 100);

                $animal->events()->create([
                    'event_type' => 'weighing', 'event_date' => $date->toDateString(), 'title' => 'Pesaje registrado',
                    'weight_kg' => round($weight, 1), 'description' => $isYoung ? 'Pesaje mensual de control de crecimiento.' : 'Pesaje con báscula en el corral.',
                ]);
            }

            $animal->syncCurrentWeightFromEvents();
        }
    }

    /* ----------------------------------------------------------------- Health */

    /**
     * @param  \Illuminate\Support\Collection<int, Animal>  $animals
     * @param  \Illuminate\Support\Collection<int, Animal>  $calves
     */
    private function seedHealth(Farm $farm, $animals, $calves): void
    {
        $cycles = collect([
            CarbonImmutable::create($this->today->year, 5, 18),
            CarbonImmutable::create($this->today->year - 1, 11, 12),
        ])->filter(fn (CarbonImmutable $date): bool => $date->lessThan($this->today));

        foreach ($cycles as $cycleDate) {
            foreach ($animals as $animal) {
                if (CarbonImmutable::parse($animal->birth_date)->greaterThanOrEqualTo($cycleDate)) {
                    continue;
                }

                $animal->events()->create([
                    'event_type' => 'vaccination', 'event_date' => $cycleDate->addDays(mt_rand(0, 4))->toDateString(), 'title' => 'Vacunación aftosa (ciclo ICA)',
                    'description' => 'Vacuna contra fiebre aftosa aplicada por el vacunador autorizado.', 'cost' => 3500,
                ]);
                $this->farmStats[$farm->id]['vaccinated']++;
            }
        }

        foreach ($animals->shuffle()->take((int) ceil($animals->count() * 0.6)) as $animal) {
            $done = $this->today->subDays(mt_rand(20, 85));
            $animal->events()->create([
                'event_type' => 'deworming', 'event_date' => $done->toDateString(), 'title' => 'Desparasitación con ivermectina',
                'description' => 'Ivermectina 1 % subcutánea, 1 ml por cada 50 kg.', 'cost' => mt_rand(4, 9) * 1000,
                'due_date' => $done->addDays(90)->toDateString(),
            ]);
        }

        foreach ($calves->take(4) as $calf) {
            $calf->events()->create([
                'event_type' => 'vaccination', 'event_date' => $this->today->subDays(mt_rand(10, 40))->toDateString(), 'title' => 'Vacuna triple (carbón sintomático, edema maligno y septicemia)',
                'cost' => 4200, 'due_date' => $this->today->addDays(mt_rand(1, 12))->toDateString(), 'description' => 'Requiere refuerzo.',
            ]);
        }

        foreach ($animals->where('sex', 'female')->shuffle()->take(2) as $cow) {
            $cow->events()->create([
                'event_type' => 'treatment', 'event_date' => $this->today->subDays(mt_rand(5, 45))->toDateString(), 'title' => 'Tratamiento de mastitis clínica',
                'description' => 'Cuarto posterior derecho. Antibiótico intramamario por 3 días; leche descartada 4 días.', 'cost' => 68000,
            ]);
        }

        $animals->shuffle()->first()?->events()->create([
            'event_type' => 'health_check', 'event_date' => $this->today->subDays(mt_rand(3, 25))->toDateString(), 'title' => 'Revisión sanitaria general',
            'description' => 'Condición corporal 3/5. Sin hallazgos.', 'cost' => 45000, 'due_date' => $this->today->addDays(mt_rand(4, 14))->toDateString(),
        ]);
    }

    /* ------------------------------------------------------------- Valuations */

    /**
     * @param  \Illuminate\Support\Collection<int, Animal>  $animals
     */
    private function seedValuations($animals): void
    {
        foreach ($animals->shuffle()->take((int) ceil($animals->count() * 0.6)) as $animal) {
            $weight = (float) ($animal->fresh()->current_weight_kg ?? 450);
            $pricePerKg = $animal->sex === 'male' ? 9200 : 8300;
            AnimalValuation::query()->create(['animal_id' => $animal->id, 'amount' => round($weight * $pricePerKg * 0.95, -4), 'valuation_date' => $this->today->subDays(mt_rand(80, 110))->toDateString(), 'notes' => 'Valoración trimestral por peso.']);
            AnimalValuation::query()->create(['animal_id' => $animal->id, 'amount' => round($weight * $pricePerKg, -4), 'valuation_date' => $this->today->subDays(mt_rand(3, 20))->toDateString(), 'notes' => 'Precio de referencia de la subasta regional.']);
        }
    }

    /* -------------------------------------------------------------- Movements */

    /**
     * @param  array<string, mixed>  $config
     * @param  \Illuminate\Support\Collection<int, Animal>  $steers
     * @param  \Illuminate\Support\Collection<int, Paddock>  $paddocks
     */
    private function seedMovements(Farm $farm, array $config, $steers, $paddocks): void
    {
        foreach ($steers->take(3) as $index => $steer) {
            $date = $this->today->subDays(25 + $index * 14);
            $weight = (float) ($steer->fresh()->current_weight_kg ?? 420);
            $amount = round($weight * 9100, -3);
            $transaction = FinancialTransaction::query()->create([
                'type' => 'income', 'category' => 'animal_sales', 'title' => "Venta novillo {$steer->ear_tag}", 'amount' => $amount, 'transaction_date' => $date->toDateString(),
                'farm_id' => $farm->id, 'animal_id' => $steer->id, 'payment_method' => 'bank_transfer', 'reference' => 'FV-'.mt_rand(10000, 99999),
            ]);
            AnimalMovement::query()->create([
                'movement_type' => 'sale', 'movement_date' => $date->toDateString(), 'animal_id' => $steer->id, 'animal_ear_tag' => $steer->ear_tag,
                'from_farm_id' => $farm->id, 'from_farm_name' => $farm->name, 'from_paddock_id' => $steer->paddock_id, 'amount' => $amount,
                'exit_reason' => 'market', 'counterparty_name' => 'Frigorífico Guadalupe S.A.S.', 'reference' => $transaction->reference,
                'notes' => "Vendido en pie con {$weight} kg.", 'financial_transaction_id' => $transaction->id,
            ]);
            $steer->forceFill(['status' => 'sold', 'paddock_id' => null])->save();
        }

        if ($config['profile'] === 'dairy') {
            foreach (range(1, 2) as $index) {
                $date = $this->today->subDays(60 + $index * 9);
                $cost = mt_rand(48, 62) * 100000;
                $heifer = $this->createAnimal($farm, $paddocks[3], [
                    'sex' => 'female', 'breed' => 'Holstein', 'birth_date' => $this->today->subMonths(mt_rand(20, 24)), 'purpose' => 'dairy',
                    'acquisition_type' => 'purchased', 'acquisition_date' => $date, 'acquisition_cost' => $cost, 'supplier_name' => 'Hacienda San Isidro (Belmira)',
                ]);
                $transaction = FinancialTransaction::query()->create([
                    'type' => 'expense', 'category' => 'animal_purchase', 'title' => "Compra novilla {$heifer->ear_tag}", 'amount' => $cost,
                    'transaction_date' => $date->toDateString(), 'farm_id' => $farm->id, 'animal_id' => $heifer->id, 'payment_method' => 'bank_transfer',
                    'reference' => 'CC-'.mt_rand(10000, 99999),
                ]);
                AnimalMovement::query()->create([
                    'movement_type' => 'purchase', 'movement_date' => $date->toDateString(), 'animal_id' => $heifer->id, 'animal_ear_tag' => $heifer->ear_tag,
                    'to_farm_id' => $farm->id, 'to_farm_name' => $farm->name, 'to_paddock_id' => $paddocks[3]->id, 'amount' => $cost,
                    'counterparty_name' => 'Hacienda San Isidro (Belmira)', 'reference' => $transaction->reference, 'financial_transaction_id' => $transaction->id,
                ]);
                $this->seedWeights(collect([$heifer]));
            }
        }

        $moved = Animal::query()->withoutGlobalScopes()->where('farm_id', $farm->id)->where('status', 'active')->where('paddock_id', $paddocks[0]->id)->limit(2)->get();
        foreach ($moved as $animal) {
            AnimalMovement::query()->create([
                'movement_type' => 'paddock_transfer', 'movement_date' => $this->today->subDays(mt_rand(3, 20))->toDateString(), 'animal_id' => $animal->id,
                'animal_ear_tag' => $animal->ear_tag, 'from_farm_id' => $farm->id, 'to_farm_id' => $farm->id, 'from_farm_name' => $farm->name, 'to_farm_name' => $farm->name,
                'from_paddock_id' => $paddocks[0]->id, 'to_paddock_id' => $paddocks[1]->id, 'notes' => 'Rotación de potreros.',
            ]);
            $animal->forceFill(['paddock_id' => $paddocks[1]->id, 'location' => $paddocks[1]->name])->save();
        }
    }

    private function seedTransfers(): void
    {
        $from = Farm::query()->withoutGlobalScopes()->where('code', 'DEMO-ESP')->first();
        $to = Farm::query()->withoutGlobalScopes()->where('code', 'DEMO-SRO')->first();
        $toPaddock = Paddock::query()->withoutGlobalScopes()->where('farm_id', $to->id)->orderBy('id')->skip(2)->first();
        $animals = Animal::query()->withoutGlobalScopes()->where('farm_id', $from->id)->where('sex', 'female')->where('status', 'active')
            ->whereBetween('birth_date', [$this->today->subMonths(30)->toDateString(), $this->today->subMonths(12)->toDateString()])->limit(2)->get();

        foreach ($animals as $animal) {
            AnimalMovement::query()->create([
                'movement_type' => 'farm_transfer', 'movement_date' => $this->today->subDays(mt_rand(10, 40))->toDateString(), 'animal_id' => $animal->id,
                'animal_ear_tag' => $animal->ear_tag, 'from_farm_id' => $from->id, 'to_farm_id' => $to->id, 'from_farm_name' => $from->name, 'to_farm_name' => $to->name,
                'from_paddock_id' => $animal->paddock_id, 'to_paddock_id' => $toPaddock?->id, 'notes' => 'Novilla enviada a levante en sabana.',
            ]);
            $animal->forceFill(['farm_id' => $to->id, 'paddock_id' => $toPaddock?->id, 'location' => $toPaddock?->name])->save();
        }
    }

    /* -------------------------------------------------------------- Reminders */

    /**
     * @param  \Illuminate\Support\Collection<int, Paddock>  $paddocks
     */
    private function seedReminders(Farm $farm, $paddocks): void
    {
        $reminders = [
            [2, 'Comprar sal mineralizada', '20 bultos de sal mineralizada al 8 %.'],
            [4, 'Palpación de vacas servidas', 'Visita del médico veterinario para diagnóstico de preñez.'],
            [7, 'Segundo ciclo de vacunación aftosa', 'Tener listo el corral y el registro ICA de los animales.'],
            [11, 'Mantenimiento de cercas', "Revisar cerca eléctrica del potrero {$paddocks[1]->name}."],
            [16, 'Destete de terneros', 'Separar terneros mayores de 8 meses.'],
        ];

        foreach ($reminders as [$days, $title, $description]) {
            CalendarReminder::query()->create([
                'farm_id' => $farm->id, 'created_by' => $this->admin?->id, 'title' => $title, 'description' => $description,
                'event_date' => $this->today->addDays($days + mt_rand(0, 2))->toDateString(),
            ]);
        }
    }

    /* --------------------------------------------------------------- Finances */

    /**
     * @param  array<string, mixed>  $config
     */
    private function seedFinances(Farm $farm, array $config, int $animalCount): void
    {
        $profile = $config['profile'];
        $workers = ['dairy' => 3, 'dual' => 2, 'beef' => 2][$profile];
        $feed = ['dairy' => 4200000, 'dual' => 1600000, 'beef' => 1100000][$profile];
        $methods = ['cash', 'bank_transfer', 'bank_transfer', 'card'];

        for ($month = 5; $month >= 0; $month--) {
            $start = $this->today->subMonthsNoOverflow($month)->startOfMonth();
            $end = $month === 0 ? $this->today : $start->endOfMonth();
            $date = fn (int $day): string => $start->addDays(min($day, $end->day) - 1)->toDateString();
            $add = function (string $type, string $category, string $title, float $amount, string $day) use ($farm, $methods): void {
                FinancialTransaction::query()->create([
                    'type' => $type, 'category' => $category, 'title' => $title, 'amount' => round($amount, -2), 'transaction_date' => $day,
                    'farm_id' => $farm->id, 'payment_method' => $methods[array_rand($methods)], 'reference' => strtoupper(substr($category, 0, 3)).'-'.mt_rand(1000, 9999),
                ]);
            };

            if ($profile !== 'beef') {
                foreach ([[1, 15], [16, $end->day]] as [$fromDay, $toDay]) {
                    if ($fromDay > $end->day) {
                        continue;
                    }
                    $liters = (float) DB::table('milk_production_records')
                        ->join('animals', 'animals.id', '=', 'milk_production_records.animal_id')
                        ->where('animals.farm_id', $farm->id)
                        ->whereBetween('record_date', [$start->addDays($fromDay - 1)->toDateString(), $start->addDays($toDay - 1)->toDateString()])
                        ->sum('total_liters');
                    if ($liters > 0) {
                        $add('income', 'milk_sales', 'Pago quincena de leche — '.($profile === 'dairy' ? 'Colanta' : 'Acopio La Esperanza'), $liters * $config['milk_price'], $date($toDay));
                    }
                }
            }

            $add('expense', 'labor', "Nómina {$workers} trabajadores", $workers * 1650000, $date(28));
            $add('expense', 'feed', $profile === 'dairy' ? 'Concentrado lechero 70 bultos' : 'Sal mineralizada y melaza', $feed * (mt_rand(90, 110) / 100), $date(mt_rand(3, 8)));
            $add('expense', 'utilities', 'Energía y agua', mt_rand(22, 48) * 10000, $date(mt_rand(10, 14)));

            if (mt_rand(1, 100) <= 60) {
                $add('expense', 'veterinary', 'Medicamentos y visita veterinaria', mt_rand(25, 90) * 10000, $date(mt_rand(5, 25)));
            }
            if ($month % 3 === 1) {
                $add('expense', 'fertilizers', 'Fertilización de potreros (urea y DAP)', mt_rand(12, 25) * 100000, $date(mt_rand(6, 20)));
            }
            if (mt_rand(1, 100) <= 40) {
                $add('expense', 'maintenance', ['Arreglo de bebederos', 'Reparación de cerca eléctrica', 'Mantenimiento equipo de ordeño'][mt_rand(0, 2)], mt_rand(15, 60) * 10000, $date(mt_rand(5, 25)));
            }

            $inseminations = collect($this->farmStats[$farm->id]['inseminations'])->filter(fn (CarbonImmutable $day): bool => $day->between($start, $end))->count();
            if ($inseminations > 0) {
                $add('expense', 'reproduction', "Pajillas e inseminación ({$inseminations})", $inseminations * 85000, $date(mt_rand(20, 27)));
            }
            if ($start->month === 5 || ($start->month === 11 && $month > 0)) {
                $add('expense', 'vaccination', 'Ciclo de vacunación aftosa', $animalCount * 3500, $date(20));
            }
        }
    }

    /* ----------------------------------------------------------------- Photos */

    private function attachPhotos(): void
    {
        foreach ($this->photoQueue as ['animal' => $animal, 'image' => $image]) {
            $path = public_path("images/{$image}");
            if (! is_file($path)) {
                continue;
            }

            try {
                $animal->addMedia($path)->preservingOriginal()->usingFileName("{$animal->ear_tag}.".pathinfo($path, PATHINFO_EXTENSION))->toMediaCollection('photo');
            } catch (Throwable $exception) {
                $this->command?->warn("No se pudo adjuntar la foto de {$animal->ear_tag}: {$exception->getMessage()}");
            }
        }
    }

    /* ---------------------------------------------------------------- Helpers */

    private function breedName(Animal $animal): string
    {
        return array_search($animal->breed_id, $this->breedIds, true) ?: 'Mestizo';
    }

    /**
     * @param  list<string>  $names
     */
    private function nextName(array $names, int &$index): string
    {
        $name = $names[$index % count($names)];
        $round = intdiv($index, count($names));
        $index++;

        return $round === 0 ? $name : "{$name} {$round}";
    }

    private function printSummary(): void
    {
        $count = fn (string $table): int => DB::table($table)->count();

        $this->command?->info('Datos de prueba cargados:');
        $this->command?->table(['Tabla', 'Registros'], [
            ['Fincas', $count('farms')], ['Potreros', $count('paddocks')], ['Animales', $count('animals')],
            ['Eventos (pesajes, sanidad)', $count('animal_events')], ['Eventos reproductivos', $count('reproductive_events')],
            ['Registros de leche', $count('milk_production_records')], ['Movimientos financieros', $count('financial_transactions')],
            ['Compras/ventas/traslados', $count('animal_movements')], ['Valoraciones', $count('animal_valuations')], ['Recordatorios', $count('calendar_reminders')],
        ]);
        $this->command?->line('Usuarios de prueba (contraseña: '.self::DEMO_PASSWORD.'):');
        $this->command?->line('  · admin@hatosoft.test     → Administrador, las 3 fincas');
        $this->command?->line('  · operador@hatosoft.test  → Operador, solo Finca El Porvenir');
    }
}
