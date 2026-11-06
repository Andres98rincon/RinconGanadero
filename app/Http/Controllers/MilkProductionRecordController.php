<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilkProductionRecordRequest;
use App\Models\Animal;
use App\Models\MilkProductionRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MilkProductionRecordController extends Controller
{
    public function index(Animal $animal): View
    {
        $this->ensureDairyCow($animal);

        $records = $animal->productionRecords()
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->paginate(30);
        $monthTotal = $animal->productionRecords()
            ->whereBetween('record_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total_liters');
        $allTimeTotal = $animal->productionRecords()->sum('total_liters');

        return view('animals.production', compact('animal', 'records', 'monthTotal', 'allTimeTotal'));
    }

    public function store(MilkProductionRecordRequest $request, Animal $animal): RedirectResponse
    {
        $this->ensureDairyCow($animal);
        $animal->productionRecords()->create($request->validated());

        return redirect()->route('animals.production.index', $animal)->with('success', 'Producción diaria registrada correctamente.');
    }

    public function edit(Animal $animal, MilkProductionRecord $productionRecord): View
    {
        $this->ensureDairyCow($animal);

        return view('animals.production-form', compact('animal', 'productionRecord'));
    }

    public function update(
        MilkProductionRecordRequest $request,
        Animal $animal,
        MilkProductionRecord $productionRecord,
    ): RedirectResponse {
        $this->ensureDairyCow($animal);
        $productionRecord->update($request->validated());

        return redirect()->route('animals.production.index', $animal)->with('success', 'Registro de producción actualizado.');
    }

    private function ensureDairyCow(Animal $animal): void
    {
        abort_unless($animal->species === 'bovine' && $animal->sex === 'female', 404);
    }
}
