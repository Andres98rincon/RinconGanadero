<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaddockRequest;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\Paddock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaddockController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $request->user()->accessibleFarmIdsQuery()))],
            'status' => ['nullable', 'in:active,inactive'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $paddocks = Paddock::query()
            ->with('farm:id,name,code')
            ->withCount('animals')
            ->when($filters['farm_id'] ?? null, fn ($query, int $farmId) => $query->where('farm_id', $farmId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($paddockQuery) use ($search): void {
                    $paddockQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('farm', fn ($farmQuery) => $farmQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('farm_id')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('paddocks.index', [
            'paddocks' => $paddocks,
            'filters' => $filters,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code']),
            'stats' => [
                'paddocks' => Paddock::query()->count(),
                'active' => Paddock::query()->where('status', 'active')->count(),
                'animals' => Animal::query()->whereNotNull('paddock_id')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $filters = $request->validate([
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $request->user()->accessibleFarmIdsQuery()))],
        ]);

        return view('paddocks.form', [
            'paddock' => null,
            'farms' => Farm::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'selectedFarmId' => $filters['farm_id'] ?? null,
            'statuses' => Paddock::STATUSES,
        ]);
    }

    public function store(StorePaddockRequest $request): RedirectResponse
    {
        $paddock = Paddock::query()->create($request->validated());

        return redirect()->route('paddocks.index', ['farm_id' => $paddock->farm_id])
            ->with('success', 'Potrero registrado correctamente.');
    }

    public function edit(Paddock $paddock): View
    {
        return view('paddocks.form', [
            'paddock' => $paddock,
            'farms' => Farm::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'selectedFarmId' => $paddock->farm_id,
            'statuses' => Paddock::STATUSES,
        ]);
    }

    public function update(StorePaddockRequest $request, Paddock $paddock): RedirectResponse
    {
        $validated = $request->validated();

        if ($paddock->farm_id !== (int) $validated['farm_id'] && $paddock->animals()->withTrashed()->exists()) {
            return back()
                ->withInput()
                ->withErrors(['farm_id' => 'No puedes cambiar de finca un potrero que conserva animales asignados. Trasládalos primero.']);
        }

        if ($validated['status'] === 'inactive' && $paddock->status !== 'inactive' && $paddock->animals()->withTrashed()->exists()) {
            return back()
                ->withInput()
                ->withErrors(['status' => 'No puedes desactivar un potrero que conserva animales asignados. Trasládalos primero.']);
        }

        $paddock->update($validated);

        return redirect()->route('paddocks.index', ['farm_id' => $paddock->farm_id])
            ->with('success', 'Potrero actualizado correctamente.');
    }

    public function destroy(Paddock $paddock): RedirectResponse
    {
        if ($paddock->animals()->withTrashed()->exists()) {
            return back()->withErrors([
                'paddock' => 'No se puede archivar un potrero que conserva animales asignados. Trasládalos primero.',
            ]);
        }

        $farmId = $paddock->farm_id;
        $paddock->delete();

        return redirect()->route('paddocks.index', ['farm_id' => $farmId])
            ->with('success', 'Potrero archivado correctamente.');
    }

    public function options(Farm $farm): JsonResponse
    {
        abort_unless($farm->status === 'active', 404);

        return response()->json(
            $farm->paddocks()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        );
    }
}
