<?php

namespace App\Http\Controllers;

use App\Exports\AnimalExport;
use App\Exports\AnimalTemplateExport;
use App\Http\Requests\AnimalRequest;
use App\Imports\AnimalImport;
use App\Models\Animal;
use App\Models\Breed;
use App\Models\Farm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnimalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $animals = Animal::query()
            ->with(['breed', 'farm', 'media'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('ear_tag', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('microchip', 'like', "%{$search}%");
                });
            })
            ->when($filters['species'] ?? null, fn ($query, string $species) => $query->where('species', $species))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['farm_id'] ?? null, fn ($query, int $farmId) => $query->where('farm_id', $farmId))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => Animal::count(),
            'active' => Animal::where('status', 'active')->count(),
            'females' => Animal::where('sex', 'female')->where('status', 'active')->count(),
            'births' => Animal::whereDate('birth_date', '>=', now()->startOfMonth())->count(),
        ];

        return view('animals.index', [
            'animals' => $animals,
            'filters' => $filters,
            'stats' => $stats,
            'speciesOptions' => Animal::SPECIES,
            'statusOptions' => Animal::STATUSES,
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code', 'status']),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'extensions:xlsx,csv', 'max:20480'],
        ]);
        $import = new AnimalImport($request->user());

        try {
            Excel::import($import, $validated['file']);
        } catch (LaravelExcelException|SpreadsheetException $exception) {
            report($exception);

            return to_route('animals.index')
                ->with('error', 'No se pudo procesar el archivo. Verifica que sea un Excel o CSV válido con la plantilla del sistema.');
        }

        $report = $import->report();

        activity('animals')
            ->causedBy($request->user())
            ->event('import')
            ->withProperties(['imported' => $report['imported'], 'failed' => $report['failed']])
            ->log('Importó animales desde un archivo');

        if ($report['imported'] === 0 && $report['failed'] === 0) {
            return to_route('animals.index')
                ->with('import_report', $report)
                ->with('error', 'El archivo no contiene filas de animales para importar.');
        }

        return to_route('animals.index')
            ->with('import_report', $report)
            ->with(
                $report['failed'] > 0 ? 'error' : 'success',
                "Importación terminada: {$report['imported']} animales cargados y {$report['failed']} filas con errores.",
            );
    }

    public function export(Request $request, string $format): BinaryFileResponse|RedirectResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $filters = $this->validatedFilters($request);
        activity('animals')
            ->causedBy($request->user())
            ->event('export')
            ->withProperties(['format' => $format, 'filters' => $filters])
            ->log('Exportó animales');

        $writerType = $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        return Excel::download(
            new AnimalExport($filters),
            'animales-'.now()->format('Ymd-His').'.'.$format,
            $writerType,
        );
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new AnimalTemplateExport, 'plantilla-animales.xlsx', ExcelWriter::XLSX);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        return view('animals.form', $this->formData(selectedFarmId: $request->integer('farm_id') ?: null));
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(AnimalRequest $request): RedirectResponse
    // {
    //     $animal = Animal::create($request->safe()->except(['photo', 'remove_photo']));

    //     if ($request->hasFile('photo')) {
    //         $animal->addMediaFromRequest('photo')->toMediaCollection('photo');
    //         activity('animals')
    //             ->causedBy($request->user())
    //             ->performedOn($animal)
    //             ->event('photo_added')
    //             ->log('Agregó una fotografía al animal');
    //     }

    //     return redirect()->route('animals.show', $animal)->with('success', 'Animal registrado correctamente.');
    // }

    public function store(AnimalRequest $request): RedirectResponse
{
    $animal = Animal::create(
        $request->safe()->except(['photo', 'remove_photo'])
    );

    if ($request->hasFile('photo')) {
        $animal->addMediaFromRequest('photo')
            ->toMediaCollection('photo');

        activity('animals')
            ->causedBy($request->user())
            ->performedOn($animal)
            ->event('photo_added')
            ->log('Agregó una fotografía al animal');
    }

    return redirect()
        ->route('animals.show', $animal)
        ->with('success', 'Animal registrado correctamente.');
}

    /**
     * Display the specified resource.
     */
    public function show(Animal $animal): View
    {
        $animal->load(['breed', 'farm', 'paddock', 'dam', 'sire', 'media']);
        $movements = $animal->movements()
            ->with(['fromFarm:id,name,code', 'toFarm:id,name,code', 'fromPaddock:id,name,code', 'toPaddock:id,name,code'])
            ->latest('movement_date')
            ->latest('id')
            ->limit(10)
            ->get();
        $events = $animal->events()->latest('event_date')->latest('id')->paginate(10);
        $reproductiveEvents = collect();
        $productionStats = null;

        if ($animal->species === 'bovine' && $animal->sex === 'female') {
            $reproductiveEvents = $animal->reproductiveEvents()
                ->with(['sireAnimal', 'calves'])
                ->latest('event_date')
                ->latest('id')
                ->limit(5)
                ->get();
            $productionStats = [
                'month_total' => $animal->productionRecords()
                    ->whereBetween('record_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('total_liters'),
                'record_count' => $animal->productionRecords()->count(),
            ];
        }

        return view('animals.show', compact('animal', 'events', 'movements', 'reproductiveEvents', 'productionStats'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Animal $animal): View
    {
        $animal->load('media');

        return view('animals.form', $this->formData($animal));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AnimalRequest $request, Animal $animal): RedirectResponse
    {
        $animal->update($request->safe()->except(['photo', 'remove_photo']));

        if ($request->hasFile('photo')) {
            $animal->addMediaFromRequest('photo')->toMediaCollection('photo');
            activity('animals')
                ->causedBy($request->user())
                ->performedOn($animal)
                ->event('photo_updated')
                ->log('Actualizó la fotografía del animal');
        } elseif ($request->boolean('remove_photo')) {
            $animal->clearMediaCollection('photo');
            activity('animals')
                ->causedBy($request->user())
                ->performedOn($animal)
                ->event('photo_removed')
                ->log('Eliminó la fotografía del animal');
        }

        if ($animal->reproductiveEvents()->exists()) {
            $animal->syncReproductiveStatus();
        }

        return redirect()->route('animals.show', $animal)->with('success', 'Ficha actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Animal $animal): RedirectResponse
    {
        $animal->delete();

        return redirect()->route('animals.index')->with('success', 'Animal retirado del inventario.');
    }

    private function formData(?Animal $animal = null, ?int $selectedFarmId = null): array
    {
        return [
            'animal' => $animal,
            'selectedFarmId' => $selectedFarmId,
            'breeds' => Breed::query()->orderBy('species')->orderBy('name')->get(),
            'farms' => Farm::query()->orderBy('name')->get(['id', 'name', 'code', 'status']),
            'speciesOptions' => Animal::SPECIES,
            'parentAnimals' => Animal::query()
                ->when($animal, fn ($query) => $query->whereKeyNot($animal->getKey()))
                ->orderBy('ear_tag')
                ->get(['id', 'ear_tag', 'name', 'species', 'sex']),
        ];
    }

    /**
     * @return array{q?: string|null, species?: string|null, status?: string|null, farm_id?: int|null}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'species' => ['nullable', Rule::in(array_keys(Animal::SPECIES))],
            'status' => ['nullable', Rule::in(array_keys(Animal::STATUSES))],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $request->user()->accessibleFarmIdsQuery()))],
        ]);
    }
}
