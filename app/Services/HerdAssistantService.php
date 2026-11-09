<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\AnimalMovement;
use App\Models\CalendarReminder;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\MilkProductionRecord;
use App\Models\Paddock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Asistente del hato: responde preguntas en español con los datos de la propia aplicación.
 *
 * No usa servicios externos ni modelos de IA: reconoce la intención con palabras clave,
 * extrae finca, periodo, animal y sexo, y consulta la base de datos mediante los modelos,
 * que ya limitan los datos a las fincas del usuario. Cada intención exige su permiso.
 */
class HerdAssistantService
{
    /**
     * Palabras clave por intención (en minúsculas y sin tildes). El orden decide los empates.
     *
     * @var array<string, list<string>>
     */
    private const INTENTS = [
        'howto' => ['como registro', 'como registrar', 'como agrego', 'como agregar', 'como creo', 'como crear', 'como ingreso', 'como anoto', 'como cargo', 'como hago para', 'donde registro', 'donde agrego', 'como se registra', 'como se hace', 'como meto'],
        'help' => ['ayuda', 'que puedes', 'que sabes', 'que haces', 'como funcionas', 'hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'que te puedo preguntar', 'menu'],
        'milk_top' => ['mas leche', 'mejor productora', 'mejores productoras', 'mas produce', 'mas produjo', 'mas producen', 'mejores vacas', 'menos leche', 'ranking'],
        'milk' => ['leche', 'litros', 'ordeno', 'ordenos', 'produccion', 'producido', 'produjo', 'producen', 'lechera'],
        'unweighed' => ['sin pesar', 'no se han pesado', 'no han sido pesados', 'falta pesar', 'faltan por pesar', 'pendientes de pesaje', 'no pesados', 'nunca pesados'],
        'weight' => ['peso', 'pesa', 'pesan', 'kilos', 'kg', 'pesaje', 'pesajes', 'mas pesado', 'mas pesada', 'engorde'],
        'pregnant' => ['prenada', 'prenadas', 'gestante', 'gestantes', 'gestacion', 'embarazada', 'embarazadas', 'cargadas', 'carga'],
        'calvings' => ['proximos partos', 'proximo parto', 'van a parir', 'por parir', 'parir', 'partos', 'parto', 'paren', 'fecha de parto'],
        'births' => ['nacieron', 'nacimientos', 'nacidos', 'nacio', 'terneros nuevos', 'crias nuevas'],
        'reproduction' => ['reproduccion', 'reproductivo', 'reproductiva', 'vacias', 'abiertas', 'servidas', 'en celo', 'celo', 'lactando', 'lactancia', 'inseminadas'],
        'finance' => ['gasto', 'gastos', 'gaste', 'gastado', 'ingreso', 'ingresos', 'balance', 'ganancia', 'ganancias', 'utilidad', 'plata', 'dinero', 'costos', 'costo', 'finanzas', 'saldo', 'perdida', 'perdidas', 'cuanto gane', 'cuanto vendi'],
        'calendar' => ['pendiente', 'pendientes', 'recordatorio', 'recordatorios', 'agenda', 'calendario', 'que tengo', 'tareas', 'proximos eventos', 'que hay que hacer', 'vacunas pendientes', 'por hacer'],
        'movements' => ['compra', 'compras', 'compre', 'compramos', 'venta', 'ventas', 'vendidos', 'vendimos', 'traslado', 'traslados', 'movimiento', 'movimientos'],
        'paddocks' => ['potrero', 'potreros', 'lote', 'lotes', 'pastura', 'pasturas'],
        'farms' => ['cuantas fincas', 'mis fincas', 'que fincas', 'lista de fincas', 'fincas tengo', 'fincas hay'],
        'animals' => ['animales', 'animal', 'vacas', 'vaca', 'toros', 'toro', 'novillas', 'novilla', 'novillos', 'novillo', 'terneros', 'terneras', 'ternero', 'ternera', 'crias', 'hembras', 'machos', 'ganado', 'reses', 'res', 'cabezas', 'inventario', 'bovinos', 'cabras', 'ovejas', 'cerdos', 'caballos', 'bufalos', 'hato'],
    ];

    /** @var array<string, string> */
    private const PERMISSIONS = [
        'milk_top' => 'milk-production.view',
        'milk' => 'milk-production.view',
        'unweighed' => 'weights.view',
        'weight' => 'weights.view',
        'pregnant' => 'reproduction.view',
        'calvings' => 'reproduction.view',
        'births' => 'animals.view',
        'reproduction' => 'reproduction.view',
        'finance' => 'finance.view',
        'calendar' => 'animals.view',
        'movements' => 'animal-movements.view',
        'paddocks' => 'paddocks.view',
        'farms' => 'farms.view',
        'animals' => 'animals.view',
        'animal_info' => 'animals.view',
    ];

    private const MONTHS = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];

    private const STOPWORDS = ['cuantos', 'cuantas', 'cuanto', 'cuanta', 'tengo', 'tiene', 'tenemos', 'hay', 'que', 'cual', 'cuales', 'como', 'esta', 'estan', 'este', 'esta', 'para', 'por', 'con', 'del', 'las', 'los', 'una', 'uno', 'mes', 'ano', 'semana', 'dia', 'dias', 'hoy', 'ayer', 'finca', 'vaca', 'toro', 'animal', 'leche', 'peso', 'pesa', 'mas', 'menos', 'mis', 'son', 'fue', 'ver', 'dame', 'dime', 'muestrame', 'quiero', 'saber', 'sobre', 'info', 'informacion', 'datos', 'ficha'];

    private User $user;

    private string $text;

    private CarbonImmutable $today;

    /**
     * @return array{intent: string, answer: string, items: list<array{label: string, value: string, url?: string}>, link: array{label: string, url: string}|null, suggestions: list<string>}
     */
    public function answer(User $user, string $message): array
    {
        $this->user = $user;
        $this->today = CarbonImmutable::today();
        $this->text = $this->normalize($message);

        if ($this->text === '') {
            return $this->help();
        }

        $intent = $this->detectIntent();
        $animal = in_array($intent, ['animals', 'animal_info', 'milk', 'weight', 'unknown', 'pregnant', 'calvings'], true) ? $this->detectAnimal() : null;

        if ($animal && in_array($intent, ['animals', 'unknown', 'pregnant', 'calvings'], true)) {
            $intent = 'animal_info';
        }

        if (isset(self::PERMISSIONS[$intent]) && ! $this->user->can(self::PERMISSIONS[$intent])) {
            return $this->reply($intent, 'Tu usuario no tiene permiso para consultar esa información. Si la necesitas, pídele acceso al administrador.');
        }

        return match ($intent) {
            'help' => $this->help(),
            'howto' => $this->howTo(),
            'animal_info' => $this->animalInfo($animal),
            'animals' => $this->countAnimals(),
            'milk' => $this->milk($animal),
            'milk_top' => $this->milkTop(),
            'weight' => $this->weight($animal),
            'unweighed' => $this->unweighed(),
            'pregnant' => $this->pregnant(),
            'calvings' => $this->calvings(),
            'births' => $this->births(),
            'reproduction' => $this->reproductionSummary(),
            'finance' => $this->finance(),
            'calendar' => $this->calendar(),
            'movements' => $this->movements(),
            'paddocks' => $this->paddocks(),
            'farms' => $this->farms(),
            default => $this->unknown(),
        };
    }

    /* --------------------------------------------------------------- Intents */

    private function help(): array
    {
        return $this->reply(
            'help',
            '¡Hola! Soy el asistente de tu hato. Respondo con los datos de tus fincas, sin salir de la aplicación. Puedes preguntarme por inventario, leche, pesajes, reproducción, finanzas, calendario y cómo registrar cosas.',
            suggestions: $this->defaultSuggestions(),
        );
    }

    private function unknown(): array
    {
        return $this->reply(
            'unknown',
            'No entendí bien la pregunta. Intenta con frases cortas, por ejemplo: "¿cuántas vacas tengo?", "¿cuánta leche se produjo esta semana?" o escribe el número de chapeta de un animal.',
            suggestions: $this->defaultSuggestions(),
        );
    }

    private function howTo(): array
    {
        $guides = [
            ['keys' => ['leche', 'ordeno', 'produccion'], 'route' => 'milk-production.create', 'permission' => 'milk-production.create', 'title' => 'Registrar producción de leche', 'steps' => 'Entra a Producción de leche → "Registrar producción", elige la vaca, la fecha y los litros de la mañana y/o la tarde, y guarda. Funciona aunque no tengas internet.'],
            ['keys' => ['peso', 'pesaje', 'pesar'], 'route' => 'weights.create', 'permission' => 'weights.create', 'title' => 'Registrar un pesaje', 'steps' => 'Entra a Pesajes → "Registrar pesaje", elige el animal, la fecha y el peso en kg, y guarda. El peso actual del animal se actualiza solo.'],
            ['keys' => ['parto', 'celo', 'servicio', 'monta', 'insemin', 'prenez', 'diagnostico', 'reproduc', 'aborto'], 'route' => 'reproduction.create', 'permission' => 'reproduction.create', 'title' => 'Registrar un evento reproductivo', 'steps' => 'Entra a Reproducción → "Registrar evento", elige la vaca y el tipo (celo, monta, inseminación, diagnóstico, parto o aborto) y completa los datos.'],
            ['keys' => ['gasto', 'ingreso', 'pago', 'factura', 'compra de insumo'], 'route' => 'finance.transactions.create', 'permission' => 'finance.transactions.create', 'title' => 'Registrar un gasto o ingreso', 'steps' => 'Entra a Finanzas → "Registrar movimiento", elige si es gasto o ingreso, la categoría, el valor y la fecha. Puedes adjuntar la factura.'],
            ['keys' => ['venta', 'vender', 'compra', 'comprar', 'traslado', 'trasladar', 'mover'], 'route' => 'animal-movements.create', 'permission' => 'animal-movements.create', 'title' => 'Registrar compra, venta o traslado', 'steps' => 'Entra a Compras, ventas y traslados → "Registrar movimiento", elige el tipo, el animal y los datos de la operación.'],
            ['keys' => ['recordatorio', 'calendario', 'agenda', 'tarea'], 'route' => 'calendar.index', 'permission' => 'animals.update', 'title' => 'Crear un recordatorio', 'steps' => 'Entra al Calendario ganadero y usa el formulario "Nuevo recordatorio": elige la finca, la fecha y escribe qué hay que hacer.'],
            ['keys' => ['potrero', 'lote'], 'route' => 'paddocks.create', 'permission' => 'paddocks.create', 'title' => 'Registrar un potrero', 'steps' => 'Entra a Potreros → "Registrar potrero", elige la finca y escribe el código y el nombre.'],
            ['keys' => ['finca'], 'route' => 'farms.create', 'permission' => 'farms.create', 'title' => 'Registrar una finca', 'steps' => 'Entra a Fincas → "Registrar finca", completa el código, el nombre y, si quieres, marca la ubicación en el mapa.'],
            ['keys' => ['usuario', 'trabajador', 'empleado'], 'route' => 'admin.users.create', 'permission' => 'users.create', 'title' => 'Crear un usuario', 'steps' => 'Entra a Usuarios → "Crear usuario", escribe sus datos, elige el rol y las fincas a las que tendrá acceso.'],
            ['keys' => ['qr', 'codigo'], 'route' => 'animals.index', 'permission' => 'animals.view', 'title' => 'Buscar un animal con el código QR', 'steps' => 'Entra a Inventario animal y toca "Escanear con cámara". Apunta al código QR del animal y se abrirá su ficha.'],
            ['keys' => ['animal', 'vaca', 'toro', 'ternero', 'ternera', 'novill', 'reses', 'cria'], 'route' => 'animals.create', 'permission' => 'animals.create', 'title' => 'Registrar un animal', 'steps' => 'Entra a Inventario animal → "Registrar animal", completa la chapeta, especie, sexo, fecha de nacimiento y la finca. La foto es opcional.'],
        ];

        foreach ($guides as $guide) {
            if (! $this->containsAny($guide['keys'])) {
                continue;
            }

            if (! $this->user->can($guide['permission'])) {
                return $this->reply('howto', "Para «{$guide['title']}» necesitas un permiso que tu usuario no tiene. Pídeselo al administrador.");
            }

            return $this->reply('howto', $guide['steps'], link: $this->link('Ir a '.mb_strtolower($guide['title']), $guide['route']));
        }

        return $this->reply('howto', 'Puedo explicarte cómo registrar animales, leche, pesajes, eventos reproductivos, gastos, ventas, recordatorios, potreros y fincas. ¿Qué quieres registrar?', suggestions: ['¿Cómo registro la leche?', '¿Cómo registro un pesaje?', '¿Cómo registro un parto?', '¿Cómo registro un gasto?']);
    }

    private function countAnimals(): array
    {
        $farm = $this->detectFarm();
        $status = match (true) {
            $this->containsAny(['vendido', 'vendida']) => 'sold',
            $this->containsAny(['muerto', 'muerta', 'muertos', 'murieron', 'fallecido', 'fallecida']) => 'deceased',
            $this->containsAny(['extraviado', 'perdido', 'perdida']) => 'missing',
            default => 'active',
        };
        $sex = $this->detectSex();
        $species = $this->detectSpecies();
        $ageRange = $this->detectAgeGroup();

        $query = $this->animalQuery($farm, $status, $sex, $species, $ageRange);
        $total = (clone $query)->count();
        $description = $this->describeAnimals($sex, $species, $ageRange);
        $where = $farm ? " en {$farm->name}" : '';
        $statusText = ['active' => '', 'sold' => ' vendidos', 'deceased' => ' fallecidos', 'missing' => ' extraviados'][$status];

        $answer = $status === 'active'
            ? "Tienes {$this->number($total)} {$description}{$where}."
            : "Hay {$this->number($total)} {$description}{$statusText}{$where}.";

        $items = [];

        if ($total > 0 && $sex === null) {
            $bySex = (clone $query)->toBase()->selectRaw('sex, COUNT(*) as total')->groupBy('sex')->pluck('total', 'sex');
            foreach (['female' => 'Hembras', 'male' => 'Machos', 'unknown' => 'Sin definir'] as $key => $label) {
                if (($bySex[$key] ?? 0) > 0) {
                    $items[] = ['label' => $label, 'value' => $this->number($bySex[$key])];
                }
            }
        }

        if ($total > 0 && $farm === null && $this->user->can('farms.view')) {
            $byFarm = (clone $query)->toBase()->selectRaw('farm_id, COUNT(*) as total')->groupBy('farm_id')->orderByDesc('total')->limit(5)->pluck('total', 'farm_id');
            $farmNames = Farm::query()->whereIn('id', $byFarm->keys()->filter())->pluck('name', 'id');
            foreach ($byFarm as $farmId => $count) {
                $items[] = ['label' => $farmNames[$farmId] ?? 'Sin finca', 'value' => $this->number($count)];
            }
        }

        return $this->reply(
            'animals',
            $answer,
            $items,
            $this->link('Ver en el inventario', 'animals.index', array_filter(['status' => $status, 'species' => $species, 'farm_id' => $farm?->id])),
            ['¿Cuántas vacas preñadas hay?', '¿Cuánta leche se produjo este mes?', '¿Qué animales no se han pesado?'],
        );
    }

    private function animalInfo(Animal $animal): array
    {
        $animal->loadMissing(['farm', 'breed']);
        $name = $animal->name ? "{$animal->ear_tag} · {$animal->name}" : $animal->ear_tag;
        $sexLabel = ['female' => 'Hembra', 'male' => 'Macho'][$animal->sex] ?? 'Sin definir';
        $items = [
            ['label' => 'Especie', 'value' => \App\Models\Species::label($animal->species)],
            ['label' => 'Sexo', 'value' => $sexLabel],
            ['label' => 'Estado', 'value' => Animal::STATUSES[$animal->status] ?? $animal->status],
        ];

        if ($animal->breed) {
            $items[] = ['label' => 'Raza', 'value' => $animal->breed->name];
        }
        if ($animal->birth_date) {
            $items[] = ['label' => 'Edad', 'value' => $this->age($animal->birth_date)];
        }
        if ($animal->farm) {
            $items[] = ['label' => 'Finca', 'value' => $animal->farm->name];
        }
        if ($animal->current_weight_kg !== null && $this->user->can('weights.view')) {
            $items[] = ['label' => 'Peso actual', 'value' => $this->kg($animal->current_weight_kg).($animal->last_weighed_at ? ' ('.$animal->last_weighed_at->format('d/m/Y').')' : '')];
        }
        if ($animal->reproductive_status && $this->user->can('reproduction.view')) {
            $items[] = ['label' => 'Reproducción', 'value' => $this->reproductiveLabel($animal->reproductive_status)];
        }
        if ($animal->expected_calving_date && $this->user->can('reproduction.view')) {
            $items[] = ['label' => 'Parto probable', 'value' => $animal->expected_calving_date->format('d/m/Y')];
        }
        if ($animal->sex === 'female' && $this->user->can('milk-production.view')) {
            $liters = (float) $animal->productionRecords()->where('record_date', '>=', $this->today->subDays(29)->toDateString())->sum('total_liters');
            if ($liters > 0) {
                $items[] = ['label' => 'Leche últimos 30 días', 'value' => $this->liters($liters)];
            }
        }

        return $this->reply('animal_info', "Esta es la ficha resumida de {$name}.", $items, $this->link('Abrir ficha completa', 'animals.show', ['animal' => $animal->id]), ["¿Cuánta leche dio {$animal->ear_tag} este mes?", "¿Cuánto pesa {$animal->ear_tag}?"]);
    }

    private function milk(?Animal $animal): array
    {
        [$from, $to, $periodLabel] = $this->detectPeriod('this_month');
        $farm = $this->detectFarm();
        $query = MilkProductionRecord::query()
            ->whereBetween('record_date', $this->dateRange($from, $to))
            ->when($animal, fn (Builder $query) => $query->where('animal_id', $animal->id))
            ->when($farm, fn (Builder $query) => $query->whereHas('animal', fn (Builder $animals) => $animals->where('farm_id', $farm->id)));

        $stats = (clone $query)->toBase()
            ->selectRaw('COALESCE(SUM(total_liters), 0) as liters, COUNT(*) as records, COUNT(DISTINCT animal_id) as cows, COUNT(DISTINCT record_date) as days')
            ->first();
        $liters = (float) $stats->liters;
        $subject = $animal ? ($animal->name ?: $animal->ear_tag) : ($farm ? "en {$farm->name}" : 'en total');

        if ($stats->records == 0) {
            return $this->reply('milk', "No hay producción de leche registrada {$periodLabel}".($animal ? " para {$subject}" : ($farm ? " {$subject}" : '')).'.', link: $this->link('Registrar producción', 'milk-production.create'), suggestions: ['¿Cuánta leche se produjo el mes pasado?', '¿Cómo registro la leche?']);
        }

        $answer = $animal
            ? "{$subject} produjo {$this->liters($liters)} {$periodLabel}."
            : "Se produjeron {$this->liters($liters)} {$subject} {$periodLabel}.";
        $items = [
            ['label' => 'Días con registro', 'value' => $this->number($stats->days)],
            ['label' => 'Promedio por día', 'value' => $this->liters($stats->days > 0 ? $liters / $stats->days : 0)],
        ];

        if (! $animal) {
            $items[] = ['label' => 'Vacas en ordeño', 'value' => $this->number($stats->cows)];
            $items[] = ['label' => 'Promedio por vaca al día', 'value' => $this->liters($stats->records > 0 ? $liters / $stats->records : 0)];
        }

        return $this->reply('milk', $answer, $items, $this->link('Ver producción de leche', 'milk-production.index'), ['¿Cuál vaca produjo más leche este mes?', '¿Cuánta leche se produjo la semana pasada?']);
    }

    private function milkTop(): array
    {
        [$from, $to, $periodLabel] = $this->detectPeriod('this_month');
        $farm = $this->detectFarm();
        $lowest = $this->containsAny(['menos leche', 'menos produce', 'peor']);
        $rows = MilkProductionRecord::query()
            ->whereBetween('record_date', $this->dateRange($from, $to))
            ->when($farm, fn (Builder $query) => $query->whereHas('animal', fn (Builder $animals) => $animals->where('farm_id', $farm->id)))
            ->toBase()
            ->selectRaw('animal_id, SUM(total_liters) as liters')
            ->groupBy('animal_id')
            ->orderBy('liters', $lowest ? 'asc' : 'desc')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return $this->reply('milk_top', "No hay producción registrada {$periodLabel} para comparar vacas.", link: $this->link('Ver producción de leche', 'milk-production.index'));
        }

        $animals = Animal::query()->whereIn('id', $rows->pluck('animal_id'))->get(['id', 'ear_tag', 'name'])->keyBy('id');
        $items = $rows->map(fn (object $row): array => [
            'label' => $this->animalName($animals[$row->animal_id] ?? null),
            'value' => $this->liters((float) $row->liters),
            'url' => isset($animals[$row->animal_id]) ? route('animals.show', $row->animal_id) : null,
        ])->map(fn (array $item): array => array_filter($item))->values()->all();
        $first = $items[0];

        return $this->reply('milk_top', ($lowest ? 'La vaca que menos leche produjo ' : 'La vaca que más leche produjo ')."{$periodLabel} fue {$first['label']} con {$first['value']}. Este es el top:", $items, $this->link('Ver producción de leche', 'milk-production.index'));
    }

    private function weight(?Animal $animal): array
    {
        if ($animal) {
            if ($animal->current_weight_kg === null) {
                return $this->reply('weight', "{$this->animalName($animal)} todavía no tiene pesajes registrados.", link: $this->link('Registrar pesaje', 'weights.create'));
            }

            $previous = AnimalEvent::query()->where('animal_id', $animal->id)->whereNotNull('weight_kg')->latest('event_date')->latest('id')->skip(1)->first();
            $items = [['label' => 'Último pesaje', 'value' => $animal->last_weighed_at?->format('d/m/Y') ?? '—']];
            if ($previous) {
                $difference = (float) $animal->current_weight_kg - (float) $previous->weight_kg;
                $items[] = ['label' => 'Pesaje anterior', 'value' => $this->kg($previous->weight_kg).' ('.$previous->event_date->format('d/m/Y').')'];
                $items[] = ['label' => 'Cambio', 'value' => ($difference >= 0 ? '+' : '').$this->kg($difference)];
            }

            return $this->reply('weight', "{$this->animalName($animal)} pesa {$this->kg($animal->current_weight_kg)}.", $items, $this->link('Abrir ficha', 'animals.show', ['animal' => $animal->id]));
        }

        $farm = $this->detectFarm();
        $query = $this->animalQuery($farm, 'active', $this->detectSex(), $this->detectSpecies(), $this->detectAgeGroup())->whereNotNull('current_weight_kg');
        $stats = (clone $query)->toBase()->selectRaw('COUNT(*) as animals, AVG(current_weight_kg) as average, MAX(current_weight_kg) as maximum, MIN(current_weight_kg) as minimum')->first();

        if ($stats->animals == 0) {
            return $this->reply('weight', 'Todavía no hay animales con peso registrado'.($farm ? " en {$farm->name}" : '').'.', link: $this->link('Registrar pesaje', 'weights.create'));
        }

        $heaviest = (clone $query)->orderByDesc('current_weight_kg')->limit(3)->get(['id', 'ear_tag', 'name', 'current_weight_kg']);

        return $this->reply(
            'weight',
            "El peso promedio es {$this->kg($stats->average)} en {$this->number($stats->animals)} animales pesados".($farm ? " de {$farm->name}" : '').'.',
            array_merge(
                [['label' => 'Más liviano', 'value' => $this->kg($stats->minimum)]],
                $heaviest->map(fn (Animal $item): array => ['label' => 'Pesado: '.$this->animalName($item), 'value' => $this->kg($item->current_weight_kg), 'url' => route('animals.show', $item)])->all(),
            ),
            $this->link('Ver pesajes', 'weights.index'),
            ['¿Qué animales no se han pesado?'],
        );
    }

    private function unweighed(): array
    {
        $days = preg_match('/(\d{1,3})\s*dias/', $this->text, $match) ? max(1, (int) $match[1]) : 60;
        $limitDate = $this->today->subDays($days)->toDateString();
        $query = $this->animalQuery($this->detectFarm(), 'active', $this->detectSex(), $this->detectSpecies(), null)
            ->where(fn (Builder $query) => $query->whereNull('last_weighed_at')->orWhere('last_weighed_at', '<', $limitDate));
        $total = (clone $query)->count();
        $animals = (clone $query)->orderByRaw('last_weighed_at IS NULL DESC')->orderBy('last_weighed_at')->limit(6)->get(['id', 'ear_tag', 'name', 'last_weighed_at']);

        if ($total === 0) {
            return $this->reply('unweighed', "¡Bien! Todos los animales activos tienen un pesaje en los últimos {$days} días.");
        }

        return $this->reply(
            'unweighed',
            "Hay {$this->number($total)} animales sin pesar en los últimos {$days} días.".($total > 6 ? ' Estos son los más atrasados:' : ''),
            $animals->map(fn (Animal $animal): array => ['label' => $this->animalName($animal), 'value' => $animal->last_weighed_at ? 'Último: '.$animal->last_weighed_at->format('d/m/Y') : 'Nunca pesado', 'url' => route('animals.show', $animal)])->all(),
            $this->user->can('weights.create') ? $this->link('Registrar pesaje', 'weights.create') : null,
        );
    }

    private function pregnant(): array
    {
        $farm = $this->detectFarm();
        $query = $this->animalQuery($farm, 'active', 'female', null, null)->where('reproductive_status', 'pregnant');
        $total = (clone $query)->count();
        $animals = (clone $query)->orderByRaw('expected_calving_date IS NULL')->orderBy('expected_calving_date')->limit(6)->get(['id', 'ear_tag', 'name', 'expected_calving_date']);

        if ($total === 0) {
            return $this->reply('pregnant', 'No hay hembras registradas como preñadas'.($farm ? " en {$farm->name}" : '').'.', link: $this->link('Ver reproducción', 'reproduction.index'));
        }

        return $this->reply(
            'pregnant',
            "Hay {$this->number($total)} ".($total === 1 ? 'hembra preñada' : 'hembras preñadas').($farm ? " en {$farm->name}" : '').'. Las más próximas a parir:',
            $animals->map(fn (Animal $animal): array => ['label' => $this->animalName($animal), 'value' => $animal->expected_calving_date ? 'Parto: '.$animal->expected_calving_date->format('d/m/Y') : 'Sin fecha probable', 'url' => route('animals.show', $animal)])->all(),
            $this->link('Ver reproducción', 'reproduction.index'),
            ['¿Qué partos vienen este mes?'],
        );
    }

    private function calvings(): array
    {
        [$from, $to, $periodLabel] = $this->detectPeriod('next_30_days');
        $from = $from->lessThan($this->today) && ! $this->containsAny(['pasad', 'anterior', 'ayer']) ? $this->today : $from;
        $animals = $this->animalQuery($this->detectFarm(), 'active', 'female', null, null)
            ->whereBetween('expected_calving_date', $this->dateRange($from, $to))
            ->orderBy('expected_calving_date')
            ->limit(10)
            ->get(['id', 'ear_tag', 'name', 'expected_calving_date']);

        if ($animals->isEmpty()) {
            return $this->reply('calvings', "No hay partos probables {$periodLabel}.", link: $this->link('Ver reproducción', 'reproduction.index'), suggestions: ['¿Cuántas vacas preñadas hay?']);
        }

        return $this->reply(
            'calvings',
            "Hay {$this->number($animals->count())} ".($animals->count() === 1 ? 'parto probable' : 'partos probables')." {$periodLabel}:",
            $animals->map(fn (Animal $animal): array => ['label' => $this->animalName($animal), 'value' => $animal->expected_calving_date->format('d/m/Y').' · '.$this->relativeDays($animal->expected_calving_date), 'url' => route('animals.show', $animal)])->all(),
            $this->link('Ver calendario', 'calendar.index'),
        );
    }

    private function births(): array
    {
        [$from, $to, $periodLabel] = $this->detectPeriod('this_month');
        $query = $this->animalQuery($this->detectFarm(), null, $this->detectSex(), $this->detectSpecies(), null)
            ->whereBetween('birth_date', $this->dateRange($from, $to));
        $total = (clone $query)->count();
        $females = (clone $query)->where('sex', 'female')->count();

        return $this->reply(
            'births',
            $total === 0 ? "No hay nacimientos registrados {$periodLabel}." : "Nacieron {$this->number($total)} ".($total === 1 ? 'cría' : 'crías')." {$periodLabel}.",
            $total === 0 ? [] : [['label' => 'Hembras', 'value' => $this->number($females)], ['label' => 'Machos', 'value' => $this->number($total - $females)]],
            $this->link('Ver inventario', 'animals.index'),
        );
    }

    private function reproductionSummary(): array
    {
        $farm = $this->detectFarm();
        $counts = $this->animalQuery($farm, 'active', 'female', null, null)->toBase()
            ->selectRaw('reproductive_status, COUNT(*) as total')->groupBy('reproductive_status')->pluck('total', 'reproductive_status');
        $items = collect(['pregnant', 'served', 'lactating', 'open'])
            ->map(fn (string $status): array => ['label' => $this->reproductiveLabel($status), 'value' => $this->number($counts[$status] ?? 0)])
            ->push(['label' => 'Sin registro reproductivo', 'value' => $this->number($counts[''] ?? 0)])
            ->all();

        return $this->reply('reproduction', 'Así está el estado reproductivo de tus hembras activas'.($farm ? " en {$farm->name}" : '').':', $items, $this->link('Ver reproducción', 'reproduction.index'), ['¿Qué partos vienen este mes?']);
    }

    private function finance(): array
    {
        [$from, $to, $periodLabel] = $this->detectPeriod('this_month');
        $farm = $this->detectFarm();
        $query = FinancialTransaction::query()
            ->whereBetween('transaction_date', $this->dateRange($from, $to))
            ->when($farm, fn (Builder $query) => $query->where('farm_id', $farm->id));
        $totals = (clone $query)->toBase()->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income, COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses")->first();
        $income = (float) $totals->income;
        $expenses = (float) $totals->expenses;
        $balance = $income - $expenses;
        $where = $farm ? " en {$farm->name}" : '';

        if ($this->containsAny(['en que gaste', 'en que se fue', 'mayor gasto', 'mas gaste', 'categoria', 'categorias', 'en que gastamos'])) {
            $categories = (clone $query)->where('type', 'expense')->toBase()->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->limit(5)->pluck('total', 'category');
            $labels = collect(FinancialTransaction::CATEGORIES)->collapse();

            return $this->reply('finance', $categories->isEmpty() ? "No hay gastos registrados {$periodLabel}{$where}." : "Estos fueron tus mayores gastos {$periodLabel}{$where}:", $categories->map(fn ($total, $category): array => ['label' => $labels[$category] ?? Str::headline($category), 'value' => $this->money($total)])->values()->all(), $this->link('Ver finanzas', 'finance.index'));
        }

        $answer = match (true) {
            $this->containsAny(['balance', 'ganancia', 'ganancias', 'utilidad', 'saldo', 'perdida', 'perdidas', 'cuanto gane']) => $balance >= 0
                ? "Tu balance {$periodLabel}{$where} es positivo: ganaste {$this->money($balance)}."
                : 'Tu balance '."{$periodLabel}{$where} es negativo: los gastos superan los ingresos en {$this->money(abs($balance))}.",
            $this->containsAny(['ingreso', 'ingresos', 'cuanto vendi', 'entro', 'recibi']) => "Tus ingresos {$periodLabel}{$where} fueron {$this->money($income)}.",
            default => "Tus gastos {$periodLabel}{$where} fueron {$this->money($expenses)}.",
        };

        return $this->reply('finance', $answer, [
            ['label' => 'Ingresos', 'value' => $this->money($income)],
            ['label' => 'Gastos', 'value' => $this->money($expenses)],
            ['label' => 'Balance', 'value' => ($balance < 0 ? '-' : '').$this->money(abs($balance))],
        ], $this->link('Ver finanzas', 'finance.index'), ['¿En qué gasté más este mes?', '¿Cuál es el balance del año?']);
    }

    private function calendar(): array
    {
        $to = $this->today->addDays(14);
        $events = collect();

        CalendarReminder::query()->with('animal:id,ear_tag,name')->whereBetween('event_date', $this->dateRange($this->today, $to))->orderBy('event_date')->limit(10)->get()
            ->each(fn (CalendarReminder $reminder) => $events->push(['date' => $reminder->event_date, 'label' => $reminder->title.($reminder->animal ? ' · '.$this->animalName($reminder->animal) : '')]));
        AnimalEvent::query()->with('animal:id,ear_tag,name')->whereBetween('due_date', $this->dateRange($this->today, $to))->orderBy('due_date')->limit(10)->get()
            ->each(fn (AnimalEvent $event) => $events->push(['date' => $event->due_date, 'label' => $event->title.' · '.$this->animalName($event->animal)]));

        if ($this->user->can('reproduction.view')) {
            Animal::query()->where('status', 'active')->whereBetween('expected_calving_date', $this->dateRange($this->today, $to))->limit(10)->get(['id', 'ear_tag', 'name', 'expected_calving_date'])
                ->each(fn (Animal $animal) => $events->push(['date' => $animal->expected_calving_date, 'label' => 'Parto probable · '.$this->animalName($animal)]));
        }

        $events = $events->sortBy(fn (array $event) => $event['date']->timestamp)->take(10)->values();

        if ($events->isEmpty()) {
            return $this->reply('calendar', 'No tienes pendientes en los próximos 14 días. ¡Todo al día!', link: $this->link('Abrir calendario', 'calendar.index'));
        }

        return $this->reply(
            'calendar',
            "Tienes {$this->number($events->count())} pendientes en los próximos 14 días:",
            $events->map(fn (array $event): array => ['label' => $event['label'], 'value' => $event['date']->format('d/m').' · '.$this->relativeDays($event['date'])])->all(),
            $this->link('Abrir calendario', 'calendar.index'),
        );
    }

    private function movements(): array
    {
        [$from, $to, $periodLabel] = $this->detectPeriod('this_month');
        $rows = AnimalMovement::query()->whereBetween('movement_date', $this->dateRange($from, $to))->toBase()
            ->selectRaw('movement_type, COUNT(*) as total, COALESCE(SUM(amount), 0) as amount')->groupBy('movement_type')->get()->keyBy('movement_type');
        $purchases = $rows['purchase'] ?? null;
        $sales = $rows['sale'] ?? null;
        $transfers = (int) ($rows['farm_transfer']->total ?? 0) + (int) ($rows['paddock_transfer']->total ?? 0);
        $canSeeMoney = $this->user->can('finance.view');
        $money = fn (?object $row): string => $canSeeMoney && $row && $row->amount > 0 ? ' · '.$this->money($row->amount) : '';

        return $this->reply(
            'movements',
            $rows->isEmpty() ? "No hay compras, ventas ni traslados registrados {$periodLabel}." : "Estos son los movimientos de animales {$periodLabel}:",
            $rows->isEmpty() ? [] : [
                ['label' => 'Compras', 'value' => $this->number($purchases->total ?? 0).$money($purchases)],
                ['label' => 'Ventas', 'value' => $this->number($sales->total ?? 0).$money($sales)],
                ['label' => 'Traslados', 'value' => $this->number($transfers)],
            ],
            $this->link('Ver movimientos', 'animal-movements.index'),
        );
    }

    private function paddocks(): array
    {
        $farm = $this->detectFarm();
        $paddocks = Paddock::query()->when($farm, fn (Builder $query) => $query->where('farm_id', $farm->id))->withCount(['animals' => fn (Builder $query) => $query->where('status', 'active')])->orderBy('name')->limit(8)->get();
        $total = Paddock::query()->when($farm, fn (Builder $query) => $query->where('farm_id', $farm->id))->count();

        return $this->reply(
            'paddocks',
            $total === 0 ? 'No hay potreros registrados'.($farm ? " en {$farm->name}" : '').'.' : "Tienes {$this->number($total)} ".($total === 1 ? 'potrero' : 'potreros').($farm ? " en {$farm->name}" : '').':',
            $paddocks->map(fn (Paddock $paddock): array => ['label' => $paddock->name ?: $paddock->code, 'value' => $this->number($paddock->animals_count).' animales'])->all(),
            $this->link('Ver potreros', 'paddocks.index'),
        );
    }

    private function farms(): array
    {
        $farms = Farm::query()->withCount(['animals' => fn (Builder $query) => $query->where('status', 'active')])->orderBy('name')->get();

        return $this->reply(
            'farms',
            $farms->isEmpty() ? 'No tienes fincas asignadas.' : "Tienes acceso a {$this->number($farms->count())} ".($farms->count() === 1 ? 'finca' : 'fincas').':',
            $farms->take(8)->map(fn (Farm $farm): array => ['label' => $farm->name, 'value' => $this->number($farm->animals_count).' animales', 'url' => route('farms.show', $farm)])->all(),
            $this->link('Ver fincas', 'farms.index'),
        );
    }

    /* -------------------------------------------------------------- Entities */

    private function detectIntent(): string
    {
        $bestIntent = 'unknown';
        $bestScore = 0.0;

        foreach (self::INTENTS as $intent => $keywords) {
            $score = 0.0;
            foreach ($keywords as $keyword) {
                if ($this->containsPhrase($keyword)) {
                    $score += 1 + (substr_count($keyword, ' ') * 0.75);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIntent = $intent;
            }
        }

        if ($bestIntent === 'unknown' && preg_match('/\b(cuantos|cuantas)\b/', $this->text)) {
            return 'animals';
        }

        return $bestIntent;
    }

    private function detectAnimal(): ?Animal
    {
        preg_match_all('/[a-z0-9-]+/', $this->text, $matches);
        $tokens = collect($matches[0])->unique();
        $tags = $tokens->filter(fn (string $token): bool => preg_match('/\d/', $token) === 1 && strlen($token) >= 2)->values();

        if ($tags->isNotEmpty()) {
            $animal = Animal::query()->whereIn('ear_tag', $tags->merge($tags->map(fn (string $tag): string => strtoupper($tag)))->unique()->all())->first();
            if ($animal) {
                return $animal;
            }
        }

        $names = $tokens->filter(fn (string $token): bool => strlen($token) >= 3 && ! preg_match('/\d/', $token) && ! in_array($token, self::STOPWORDS, true) && ! $this->isKeyword($token))->values();

        if ($names->isEmpty() || ! $this->containsAny(['vaca', 'toro', 'animal', 'ternero', 'ternera', 'novilla', 'novillo', 'la ', 'el ', 'ficha', 'pesa', 'leche'])) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, $names->count(), '?'));

        return Animal::query()->whereNotNull('name')->whereRaw("LOWER(name) IN ({$placeholders})", $names->all())->first();
    }

    private function detectFarm(): ?Farm
    {
        return Farm::query()->get(['id', 'name', 'code'])
            ->sortByDesc(fn (Farm $farm): int => mb_strlen($farm->name))
            ->first(function (Farm $farm): bool {
                $name = $this->normalize($farm->name);
                $shortName = trim(preg_replace('/^(finca|hacienda|hato)\s+/', '', $name));

                return ($name !== '' && $this->containsPhrase($name))
                    || (mb_strlen($shortName) >= 3 && $this->containsPhrase($shortName))
                    || $this->containsPhrase($this->normalize($farm->code));
            });
    }

    private function detectSex(): ?string
    {
        return match (true) {
            $this->containsAny(['hembra', 'vaca', 'novilla', 'ternera', 'yegua']) => 'female',
            $this->containsAny(['macho', 'toro', 'novillo', 'verraco']) => 'male',
            default => null,
        };
    }

    private function detectSpecies(): ?string
    {
        return match (true) {
            $this->containsAny(['vaca', 'vacas', 'toro', 'toros', 'novill', 'bovino', 'bovinos', 'ternero', 'ternera', 'reses']) => 'bovine',
            $this->containsAny(['cabra', 'cabras', 'caprino', 'chivo']) => 'goat',
            $this->containsAny(['oveja', 'ovejas', 'ovino', 'cordero']) => 'sheep',
            $this->containsAny(['cerdo', 'cerdos', 'porcino', 'marrano', 'lechon']) => 'pig',
            $this->containsAny(['caballo', 'caballos', 'yegua', 'equino', 'mula']) => 'horse',
            $this->containsAny(['bufalo', 'bufalos', 'bufalino']) => 'buffalo',
            default => null,
        };
    }

    /**
     * @return array{0: int, 1: int}|null minimum and maximum age in months
     */
    private function detectAgeGroup(): ?array
    {
        return match (true) {
            $this->containsAny(['ternero', 'ternera', 'terneros', 'terneras', 'crias', 'becerro']) => [0, 12],
            $this->containsAny(['novilla', 'novillas', 'novillo', 'novillos', 'levante']) => [12, 36],
            default => null,
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function detectPeriod(string $default): array
    {
        $today = $this->today;

        if (preg_match('/ultimos?\s+(\d{1,3})\s+dias/', $this->text, $match)) {
            $days = max(1, (int) $match[1]);

            return [$today->subDays($days - 1), $today, "en los últimos {$days} días"];
        }

        if (preg_match('/proximos?\s+(\d{1,3})\s+dias/', $this->text, $match)) {
            return [$today, $today->addDays((int) $match[1]), "en los próximos {$match[1]} días"];
        }

        foreach (self::MONTHS as $monthName => $month) {
            if ($this->containsPhrase($monthName)) {
                $year = preg_match('/\b(20\d{2})\b/', $this->text, $yearMatch) ? (int) $yearMatch[1] : ($month > $today->month && $default !== 'next_30_days' ? $today->year - 1 : $today->year);
                $start = CarbonImmutable::create($year, $month, 1);

                return [$start, $start->endOfMonth()->startOfDay(), 'en '.$monthName.($year !== $today->year ? " de {$year}" : '')];
            }
        }

        return match (true) {
            $this->containsPhrase('hoy') => [$today, $today, 'hoy'],
            $this->containsPhrase('ayer') => [$today->subDay(), $today->subDay(), 'ayer'],
            $this->containsAny(['semana pasada', 'la otra semana', 'semana anterior']) => [$today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()->startOfDay(), 'la semana pasada'],
            $this->containsAny(['proxima semana', 'siguiente semana']) => [$today->addWeek()->startOfWeek(), $today->addWeek()->endOfWeek()->startOfDay(), 'la próxima semana'],
            $this->containsAny(['esta semana', 'semana', 'ultima semana']) => [$today->startOfWeek(), $today, 'esta semana'],
            $this->containsAny(['mes pasado', 'mes anterior']) => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay(), 'el mes pasado'],
            $this->containsAny(['proximo mes', 'siguiente mes']) => [$today->addMonthNoOverflow()->startOfMonth(), $today->addMonthNoOverflow()->endOfMonth()->startOfDay(), 'el próximo mes'],
            $this->containsAny(['ano pasado', 'ano anterior']) => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()->startOfDay(), 'el año pasado'],
            $this->containsAny(['este ano', 'del ano', 'en el ano', 'anual', 'ano']) => [$today->startOfYear(), $today, 'en lo que va del año'],
            $this->containsAny(['este mes', 'del mes', 'mes']) => $default === 'next_30_days' ? [$today, $today->endOfMonth()->startOfDay(), 'este mes'] : [$today->startOfMonth(), $today, 'este mes'],
            $default === 'next_30_days' => [$today, $today->addDays(30), 'en los próximos 30 días'],
            default => [$today->startOfMonth(), $today, 'este mes'],
        };
    }

    /* --------------------------------------------------------------- Helpers */

    /**
     * @param  array{0: int, 1: int}|null  $ageRange
     */
    private function animalQuery(?Farm $farm, ?string $status, ?string $sex, ?string $species, ?array $ageRange): Builder
    {
        return Animal::query()
            ->when($farm, fn (Builder $query) => $query->where('farm_id', $farm->id))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($sex, fn (Builder $query) => $query->where('sex', $sex))
            ->when($species, fn (Builder $query) => $query->where('species', $species))
            ->when($ageRange, fn (Builder $query) => $query
                ->where('birth_date', '>', $this->today->subMonths($ageRange[1])->toDateString())
                ->where('birth_date', '<=', $this->today->subMonths($ageRange[0])->toDateString()));
    }

    /**
     * @param  array{0: int, 1: int}|null  $ageRange
     */
    private function describeAnimals(?string $sex, ?string $species, ?array $ageRange): string
    {
        if ($ageRange === [0, 12]) {
            return $sex === 'female' ? 'terneras' : ($sex === 'male' ? 'terneros machos' : 'terneros y terneras');
        }
        if ($ageRange === [12, 36]) {
            return $sex === 'female' ? 'novillas' : ($sex === 'male' ? 'novillos' : 'novillos y novillas');
        }
        if ($species === 'bovine') {
            return $sex === 'female' ? 'vacas y hembras bovinas' : ($sex === 'male' ? 'toros y machos bovinos' : 'bovinos');
        }
        if ($species) {
            return mb_strtolower(\App\Models\Species::label($species)).'s'.($sex === 'female' ? ' hembras' : ($sex === 'male' ? ' machos' : ''));
        }

        return $sex === 'female' ? 'hembras' : ($sex === 'male' ? 'machos' : 'animales activos');
    }

    private function reproductiveLabel(?string $status): string
    {
        return ['pregnant' => 'Preñadas / preñada', 'served' => 'Servidas (esperando diagnóstico)', 'lactating' => 'Recién paridas / lactando', 'open' => 'Vacías'][$status] ?? 'Sin registro';
    }

    /**
     * Rango de fechas que incluye el último día completo, aunque la columna guarde hora.
     *
     * @return array{0: string, 1: string}
     */
    private function dateRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->toDateString(), $to->toDateString().' 23:59:59'];
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9\s-]/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function containsPhrase(string $phrase): bool
    {
        return $phrase !== '' && preg_match('/(^|\s)'.preg_quote($phrase, '/').'(\s|$)/', $this->text) === 1;
    }

    /**
     * Matches whole words, or prefixes when the keyword is a word stem (no trailing space needed).
     *
     * @param  list<string>  $keywords
     */
    private function containsAny(array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if ($this->containsPhrase($keyword) || preg_match('/(^|\s)'.preg_quote($keyword, '/').'[a-z]*/', $this->text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isKeyword(string $token): bool
    {
        foreach (self::INTENTS as $keywords) {
            if (in_array($token, $keywords, true)) {
                return true;
            }
        }

        return false;
    }

    private function animalName(?Animal $animal): string
    {
        if (! $animal) {
            return 'Animal';
        }

        return $animal->name ? "{$animal->ear_tag} · {$animal->name}" : $animal->ear_tag;
    }

    private function age(\DateTimeInterface $birthDate): string
    {
        $months = (int) CarbonImmutable::parse($birthDate)->diffInMonths($this->today);

        if ($months < 1) {
            return 'Menos de un mes';
        }
        if ($months < 24) {
            return $months.($months === 1 ? ' mes' : ' meses');
        }

        $years = intdiv($months, 12);
        $rest = $months % 12;

        return $years.' años'.($rest > 0 ? " y {$rest} ".($rest === 1 ? 'mes' : 'meses') : '');
    }

    private function relativeDays(\DateTimeInterface $date): string
    {
        $days = (int) $this->today->diffInDays(CarbonImmutable::parse($date)->startOfDay(), false);

        return match (true) {
            $days === 0 => 'hoy',
            $days === 1 => 'mañana',
            $days === -1 => 'ayer',
            $days > 1 => "en {$days} días",
            default => 'hace '.abs($days).' días',
        };
    }

    private function number(int|float|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function liters(int|float|string|null $value): string
    {
        return number_format((float) $value, 1, ',', '.').' L';
    }

    private function kg(int|float|string|null $value): string
    {
        return number_format((float) $value, 1, ',', '.').' kg';
    }

    private function money(int|float|string|null $value): string
    {
        return '$ '.number_format((float) $value, 0, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{label: string, url: string}|null
     */
    private function link(string $label, string $route, array $parameters = []): ?array
    {
        return Route::has($route) ? ['label' => $label, 'url' => route($route, $parameters)] : null;
    }

    /**
     * @return list<string>
     */
    private function defaultSuggestions(): array
    {
        return collect([
            'animals.view' => '¿Cuántos animales tengo?',
            'milk-production.view' => '¿Cuánta leche se produjo esta semana?',
            'reproduction.view' => '¿Qué partos vienen este mes?',
            'weights.view' => '¿Qué animales no se han pesado?',
            'finance.view' => '¿Cuánto gasté este mes?',
        ])->filter(fn (string $question, string $permission): bool => $this->user->can($permission))
            ->push('¿Qué tengo pendiente?')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{label: string, value: string, url?: string|null}>  $items
     * @param  array{label: string, url: string}|null  $link
     * @param  list<string>  $suggestions
     * @return array{intent: string, answer: string, items: list<array{label: string, value: string, url?: string}>, link: array{label: string, url: string}|null, suggestions: list<string>}
     */
    private function reply(string $intent, string $answer, array $items = [], ?array $link = null, array $suggestions = []): array
    {
        return [
            'intent' => $intent,
            'answer' => $answer,
            'items' => array_values(array_map(fn (array $item): array => array_filter($item, fn ($value) => $value !== null), $items)),
            'link' => $link,
            'suggestions' => array_values($suggestions),
        ];
    }
}
