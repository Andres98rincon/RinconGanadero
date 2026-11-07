<?php

namespace App\Http\Controllers;

use App\Http\Requests\FarmRequest;
use App\AccessControl;
use App\Models\Animal;
use App\Models\Farm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FarmController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Farm::STATUSES))],
        ]);

        $farms = Farm::query()
            ->withCount('animals')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('municipality', 'like', "%{$search}%")
                        ->orWhere('manager_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => Farm::query()->count(),
            'active' => Farm::query()->where('status', 'active')->count(),
            'animals' => Animal::query()->whereNotNull('farm_id')->count(),
            'area' => Farm::query()->where('status', 'active')->sum('area_hectares'),
        ];

        return view('farms.index', [
            'farms' => $farms,
            'filters' => $filters,
            'stats' => $stats,
            'statusOptions' => Farm::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('farms.form', ['farm' => null, 'statusOptions' => Farm::STATUSES]);
    }

    public function store(FarmRequest $request): RedirectResponse
    {
        $farm = Farm::query()->create($request->validated());

        if (! $request->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            $farm->users()->attach($request->user());
        }

        return redirect()->route('farms.show', $farm)->with('success', 'Finca registrada correctamente.');
    }

    public function show(Farm $farm): View
    {
        $farm->loadCount('animals');
        $animals = $farm->animals()->with(['breed', 'paddock'])->latest('id')->paginate(15);
        $expenseYear = (int) now()->year;
        $annualExpenses = 0;
        $expenseEvolution = collect();

        if (request()->user()->can('finance.view')) {
            $yearStart = today()->startOfYear()->toDateString();
            $yearEnd = today()->toDateString();
            $annualExpenses = $farm->financialTransactions()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$yearStart, $yearEnd])
                ->sum('amount');
            $monthExpression = DB::connection()->getDriverName() === 'sqlite'
                ? "strftime('%Y-%m', transaction_date)"
                : "DATE_FORMAT(transaction_date, '%Y-%m')";
            $monthlyExpenseTotals = $farm->financialTransactions()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$yearStart, $yearEnd])
                ->selectRaw("{$monthExpression} as month, SUM(amount) as amount")
                ->groupByRaw($monthExpression)
                ->pluck('amount', 'month');
            $expenseEvolution = collect(range(1, 12))->map(fn (int $month): array => [
                'label' => Carbon::createFromDate($expenseYear, $month, 1)->locale('es')->translatedFormat('F'),
                'amount' => (float) $monthlyExpenseTotals->get(sprintf('%04d-%02d', $expenseYear, $month), 0),
            ]);
        }

        return view('farms.show', compact('farm', 'animals', 'expenseYear', 'annualExpenses', 'expenseEvolution'));
    }

    public function edit(Farm $farm): View
    {
        return view('farms.form', ['farm' => $farm, 'statusOptions' => Farm::STATUSES]);
    }

    public function update(FarmRequest $request, Farm $farm): RedirectResponse
    {
        $farm->update($request->validated());

        return redirect()->route('farms.show', $farm)->with('success', 'Finca actualizada correctamente.');
    }

    public function destroy(Farm $farm): RedirectResponse
    {
        if ($farm->animals()->withTrashed()->exists() || $farm->paddocks()->exists()) {
            return back()->withErrors([
                'farm' => 'No se puede archivar una finca que conserva animales o potreros asociados. Reasígnalos o archívalos primero.',
            ]);
        }

        $farm->delete();

        return redirect()->route('farms.index')->with('success', 'Finca archivada correctamente.');
    }
}
