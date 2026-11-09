<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Breed;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BreedController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'species_id' => ['nullable', 'integer', 'exists:species,id'],
        ]);

        $breeds = Breed::query()
            ->with('speciesCatalog')
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['species_id'] ?? null, fn ($query, int $speciesId) => $query->where('species_id', $speciesId))
            ->orderBy('species_id')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $animalCounts = Animal::query()->withoutGlobalScopes()->whereIn('breed_id', $breeds->pluck('id'))
            ->toBase()->selectRaw('breed_id, COUNT(*) as total')->groupBy('breed_id')->pluck('total', 'breed_id');

        return view('admin.breeds.index', [
            'breeds' => $breeds,
            'animalCounts' => $animalCounts,
            'species' => Species::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.breeds.form', [
            'breed' => null,
            'species' => Species::query()->orderBy('name')->get(['id', 'name']),
            'selectedSpeciesId' => $request->integer('species_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $breed = Breed::query()->create($request->validate($this->rules($request), $this->messages()));

        return to_route('admin.breeds.index', ['species_id' => $breed->species_id])->with('success', 'La raza se registró correctamente.');
    }

    public function edit(Breed $breed): View
    {
        return view('admin.breeds.form', [
            'breed' => $breed,
            'species' => Species::query()->orderBy('name')->get(['id', 'name']),
            'selectedSpeciesId' => $breed->species_id,
        ]);
    }

    public function update(Request $request, Breed $breed): RedirectResponse
    {
        $validated = $request->validate($this->rules($request, $breed), $this->messages());

        if ((int) $validated['species_id'] !== (int) $breed->species_id
            && Animal::query()->withoutGlobalScopes()->withTrashed()->where('breed_id', $breed->id)->exists()) {
            return back()->withInput()->withErrors(['species_id' => 'No puedes cambiar la especie de una raza que ya tiene animales registrados.']);
        }

        $breed->update($validated);

        return to_route('admin.breeds.index', ['species_id' => $breed->species_id])->with('success', 'La raza se actualizó correctamente.');
    }

    public function destroy(Breed $breed): RedirectResponse
    {
        if (Animal::query()->withoutGlobalScopes()->withTrashed()->where('breed_id', $breed->id)->exists()) {
            return back()->with('error', 'No se puede eliminar la raza porque tiene animales registrados.');
        }

        $breed->delete();

        return back()->with('success', 'La raza se eliminó correctamente.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request, ?Breed $breed = null): array
    {
        return [
            'species_id' => ['required', 'integer', 'exists:species,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('breeds', 'name')->where('species_id', $request->integer('species_id'))->ignore($breed)],
            'origin' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'species_id.required' => 'Selecciona la especie de la raza.',
            'name.required' => 'Escribe el nombre de la raza.',
            'name.unique' => 'Esa raza ya existe para la especie seleccionada.',
        ];
    }
}
