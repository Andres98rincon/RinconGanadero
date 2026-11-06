<?php

namespace App\Http\Controllers;

use App\AccessControl;
use App\Http\Requests\DashboardFilterRequest;
use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\ReproductiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(DashboardFilterRequest $request): View
    {
        $filters = $request->validated();
        $farmId = $filters['farm_id'] ?? null;
        $activeAnimals = $this->activeAnimals($farmId);
        $activeAnimalCount = (clone $activeAnimals)->count();
        $activeCows = (clone $activeAnimals)
            ->where('species', 'bovine')
            ->where('sex', 'female');
        $cattleCount = (clone $activeAnimals)->where('species', 'bovine')->count();
        $femaleCount = (clone $activeCows)->count();
        $averageWeight = (clone $activeAnimals)->whereNotNull('current_weight_kg')->avg('current_weight_kg');
        $pregnantCount = (clone $activeCows)->where('reproductive_status', 'pregnant')->count();
        $birthsThisMonth = $this->activeAnimals($farmId)
            ->where('birth_date', '>=', now()->startOfMonth()->toDateString())
            ->where('birth_date', '<', today()->addDay()->toDateString())
            ->count();
        $upcomingCalvings = (clone $activeCows)
            ->where('reproductive_status', 'pregnant')
            ->whereNotNull('expected_calving_date')
            ->whereDate('expected_calving_date', '<=', today()->addDays(30))
            ->orderBy('expected_calving_date')
            ->limit(5)
            ->get(['id', 'farm_id', 'ear_tag', 'name', 'expected_calving_date']);

        $milkToday = $this->milkRecords($farmId)
            ->whereDate('record_date', today()->toDateString())
            ->sum('total_liters');
        $milkYesterday = $this->milkRecords($farmId)
            ->whereDate('record_date', today()->subDay()->toDateString())
            ->sum('total_liters');
        $monthlyMilk = $this->milkRecords($farmId)
            ->where('record_date', '>=', now()->startOfMonth()->toDateString())
            ->where('record_date', '<', today()->addDay()->toDateString())
            ->sum('total_liters');
        $milkTrend = $this->milkTrend($farmId);

        $monthlyFinance = $this->financialTransactions($farmId)
            ->where('transaction_date', '>=', now()->startOfMonth()->toDateString())
            ->where('transaction_date', '<', today()->addDay()->toDateString())
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses")
            ->first();

        $speciesCounts = (clone $activeAnimals)
            ->select('species')
            ->selectRaw('COUNT(*) as animal_count')
            ->groupBy('species')
            ->orderByDesc('animal_count')
            ->get()
            ->map(fn (object $row): array => [
                'label' => Animal::SPECIES[$row->species] ?? 'Otro',
                'count' => (int) $row->animal_count,
                'percentage' => $activeAnimalCount > 0
                    ? round((int) $row->animal_count / $activeAnimalCount * 100)
                    : 0,
            ]);

        $sexCounts = (clone $activeAnimals)
            ->select('sex')
            ->selectRaw('COUNT(*) as animal_count')
            ->groupBy('sex')
            ->get()
            ->keyBy('sex');

        $topMilkProducers = $this->topMilkProducers($farmId);
        $recentActivity = $this->recentActivity($farmId);
        $financeTrend = $this->financeTrend($farmId);

        return view('dashboard.index', [
            'filters' => $filters,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
            'stats' => [
                'active_animals' => $activeAnimalCount,
                'cattle' => $cattleCount,
                'females' => $femaleCount,
                'average_weight' => (float) ($averageWeight ?? 0),
                'pregnant' => $pregnantCount,
                'calvings_due' => $upcomingCalvings->count(),
                'births' => $birthsThisMonth,
                'milk_today' => (float) $milkToday,
                'milk_yesterday' => (float) $milkYesterday,
                'milk_month' => (float) $monthlyMilk,
                'month_income' => (float) $monthlyFinance->income,
                'month_expenses' => (float) $monthlyFinance->expenses,
                'month_balance' => (float) $monthlyFinance->income - (float) $monthlyFinance->expenses,
            ],
            'milkTrend' => $milkTrend,
            'financeTrend' => $financeTrend,
            'speciesCounts' => $speciesCounts,
            'sexCounts' => $sexCounts,
            'upcomingCalvings' => $upcomingCalvings,
            'topMilkProducers' => $topMilkProducers,
            'recentActivity' => $recentActivity,
        ]);
    }

    private function activeAnimals(int|string|null $farmId): Builder
    {
        $query = DB::table('animals')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->when($farmId, fn (Builder $query, int|string $selectedFarmId) => $query->where('farm_id', $selectedFarmId));

        if (! auth()->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            $query->whereIn('farm_id', auth()->user()->accessibleFarmIdsQuery());
        }

        return $query;
    }

    private function milkRecords(int|string|null $farmId): Builder
    {
        $query = DB::table('milk_production_records')
            ->join('animals', 'milk_production_records.animal_id', '=', 'animals.id')
            ->whereNull('animals.deleted_at')
            ->where('animals.status', 'active')
            ->when($farmId, fn (Builder $query, int|string $selectedFarmId) => $query->where('animals.farm_id', $selectedFarmId));

        if (! auth()->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            $query->whereIn('animals.farm_id', auth()->user()->accessibleFarmIdsQuery());
        }

        return $query;
    }

    /**
     * @return array{points: array<int, array{label: string, value: float, x: float, y: float}>, line: string, area: string, maximum: float, total: float, comparison: float}
     */
    private function milkTrend(int|string|null $farmId): array
    {
        $startDate = today()->subDays(13);
        $dailyTotals = $this->milkRecords($farmId)
            ->where('record_date', '>=', $startDate->toDateString())
            ->where('record_date', '<', today()->addDay()->toDateString())
            ->select('record_date')
            ->selectRaw('SUM(total_liters) as total_liters')
            ->groupBy('record_date')
            ->get()
            ->keyBy(fn (object $row): string => Carbon::parse($row->record_date)->toDateString());
        $points = [];

        for ($day = 0; $day < 14; $day++) {
            $date = $startDate->copy()->addDays($day);
            $key = $date->toDateString();
            $points[] = [
                'label' => $date->format('d/m'),
                'value' => (float) ($dailyTotals->get($key)->total_liters ?? 0),
            ];
        }

        $maximum = max(array_column($points, 'value'));
        $scale = $maximum > 0 ? $maximum : 1;
        $points = array_map(
            fn (array $point, int $index): array => [
                ...$point,
                'x' => round(42 + $index * 51.2, 1),
                'y' => round(184 - ($point['value'] / $scale * 145), 1),
            ],
            $points,
            array_keys($points),
        );
        $line = implode(' ', array_map(
            fn (array $point): string => $point['x'].','.$point['y'],
            $points,
        ));
        $area = '42,184 '.$line.' 707,184';

        return [
            'points' => $points,
            'line' => $line,
            'area' => $area,
            'maximum' => $maximum,
            'total' => array_sum(array_column($points, 'value')),
            'comparison' => array_sum(array_column(array_slice($points, 7), 'value'))
                - array_sum(array_column(array_slice($points, 0, 7), 'value')),
        ];
    }

    /**
     * @return array<int, object>
     */
    private function topMilkProducers(int|string|null $farmId): array
    {
        return $this->milkRecords($farmId)
            ->where('record_date', '>=', today()->subDays(29)->toDateString())
            ->where('record_date', '<', today()->addDay()->toDateString())
            ->select('animals.id', 'animals.ear_tag', 'animals.name')
            ->selectRaw('SUM(milk_production_records.total_liters) as total_liters')
            ->groupBy('animals.id', 'animals.ear_tag', 'animals.name')
            ->orderByDesc('total_liters')
            ->limit(5)
            ->get()
            ->all();
    }

    /**
     * @return array<int, array{type: string, title: string, detail: string, date: Carbon, url: string}>
     */
    private function recentActivity(int|string|null $farmId): array
    {
        $movements = AnimalMovement::query()
            ->with('animal:id,ear_tag,name')
            ->when($farmId, function ($query, int|string $selectedFarmId): void {
                $query->where(function ($farmQuery) use ($selectedFarmId): void {
                    $farmQuery->where('from_farm_id', $selectedFarmId)
                        ->orWhere('to_farm_id', $selectedFarmId);
                });
            })
            ->latest('movement_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (AnimalMovement $movement): array => [
                'type' => $movement->movement_type === 'purchase' ? 'purchase' : 'movement',
                'title' => AnimalMovement::TYPES[$movement->movement_type] ?? 'Movimiento de ganado',
                'detail' => ($movement->animal?->ear_tag ?? $movement->animal_ear_tag)
                    .($movement->amount !== null ? ' · $'.number_format((float) $movement->amount, 0, ',', '.') : ''),
                'date' => $movement->movement_date,
                'url' => route('animal-movements.index'),
            ]);

        $events = ReproductiveEvent::query()
            ->whereHas('animal', fn ($query) => $query
                ->whereNull('deleted_at')
                ->when($farmId, fn ($animalQuery, int|string $selectedFarmId) => $animalQuery->where('farm_id', $selectedFarmId)))
            ->with('animal:id,ear_tag,name')
            ->latest('event_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (ReproductiveEvent $event): array => [
                'type' => 'reproduction',
                'title' => ReproductiveEvent::TYPES[$event->event_type] ?? 'Evento reproductivo',
                'detail' => $event->animal->ear_tag.($event->animal->name ? ' · '.$event->animal->name : ''),
                'date' => $event->event_date,
                'url' => route('reproduction.show', $event),
            ]);

        return $movements->concat($events)
            ->sortByDesc(fn (array $activity): int => $activity['date']->getTimestamp())
            ->take(7)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, income: float, expenses: float, income_height: int, expense_height: int}>
     */
    private function financeTrend(int|string|null $farmId): array
    {
        $firstMonth = today()->startOfMonth()->subMonths(5);
        $rows = $this->financialTransactions($farmId)
            ->where('transaction_date', '>=', $firstMonth->toDateString())
            ->where('transaction_date', '<', today()->addDay()->toDateString())
            ->toBase()
            ->selectRaw('SUBSTR(transaction_date, 1, 7) as month_key')
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income")
            ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expenses")
            ->groupBy('month_key')
            ->get()
            ->keyBy('month_key');
        $months = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $firstMonth->copy()->addMonths($offset);
            $row = $rows->get($month->format('Y-m'));
            $months[] = [
                'label' => mb_strtolower($month->locale('es')->translatedFormat('M')),
                'income' => (float) ($row->income ?? 0),
                'expenses' => (float) ($row->expenses ?? 0),
            ];
        }

        $maximum = max(1, ...array_column($months, 'income'), ...array_column($months, 'expenses'));

        return array_map(fn (array $month): array => [
            ...$month,
            'income_height' => $month['income'] > 0 ? max(4, (int) round($month['income'] / $maximum * 100)) : 0,
            'expense_height' => $month['expenses'] > 0 ? max(4, (int) round($month['expenses'] / $maximum * 100)) : 0,
        ], $months);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<FinancialTransaction>
     */
    private function financialTransactions(int|string|null $farmId): \Illuminate\Database\Eloquent\Builder
    {
        return FinancialTransaction::query()
            ->when($farmId, fn ($query, int|string $selectedFarmId) => $query->where('farm_id', $selectedFarmId));
    }
}
