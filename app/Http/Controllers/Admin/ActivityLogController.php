<?php

namespace App\Http\Controllers\Admin;

use App\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\AnimalMovement;
use App\Models\AnimalValuation;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\MilkProductionRecord;
use App\Models\Paddock;
use App\Models\ReproductiveEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'module' => ['nullable', Rule::in([
                'animals',
                'animal-movements',
                'farms',
                'paddocks',
                'reproduction',
                'milk-production',
                'finance',
                'users',
                'authentication',
                'backups',
            ])],
            'action' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $activityQuery = Activity::query();

        if (! $request->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            $activityQuery->where(function ($query) use ($request): void {
                foreach ($this->visibleSubjects($request->user()) as [$model, $subjects]) {
                    $query->orWhere(function ($subjectQuery) use ($model, $subjects): void {
                        $subjectQuery
                            ->where('subject_type', (new $model)->getMorphClass())
                            ->whereIn('subject_id', $subjects);
                    });
                }
            });
        }

        $activities = $activityQuery
            ->with(['causer:id,name,email', 'subject'])
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('causer_id', $userId))
            ->when($filters['module'] ?? null, fn ($query, string $module) => $query->where('log_name', $module))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('event', $action))
            ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity-log.index', [
            'activities' => $activities,
            'filters' => $filters,
            'users' => User::query()
                ->when(
                    ! $request->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE),
                    fn ($query) => $query->whereHas('farms', fn ($farmQuery) => $farmQuery
                        ->whereIn('farms.id', $request->user()->accessibleFarmIdsQuery())),
                )
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * @return list<array{0: class-string, 1: \Illuminate\Database\Eloquent\Builder}>
     */
    private function visibleSubjects(User $user): array
    {
        return [
            [Farm::class, Farm::withTrashed()->select('id')->whereIn('id', $user->accessibleFarmIdsQuery())],
            [Animal::class, Animal::withTrashed()->select('id')],
            [Paddock::class, Paddock::withTrashed()->select('id')],
            [AnimalMovement::class, AnimalMovement::query()->select('id')],
            [AnimalEvent::class, AnimalEvent::query()->select('id')],
            [AnimalValuation::class, AnimalValuation::query()->select('id')],
            [FinancialTransaction::class, FinancialTransaction::query()->select('id')],
            [MilkProductionRecord::class, MilkProductionRecord::query()->select('id')],
            [ReproductiveEvent::class, ReproductiveEvent::query()->select('id')],
            [User::class, User::query()
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', AccessControl::SUPER_ADMIN_ROLE))
                ->whereHas('farms', fn ($query) => $query->whereIn('farms.id', $user->accessibleFarmIdsQuery()))
                ->select('id')],
        ];
    }
}
