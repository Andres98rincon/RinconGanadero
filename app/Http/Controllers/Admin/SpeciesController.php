<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Species;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SpeciesController extends Controller
{
    public function index(): View
    {
        $animalCounts = Animal::query()->withoutGlobalScopes()->toBase()
            ->selectRaw('species, COUNT(*) as total')->groupBy('species')->pluck('total', 'species');

        return view('admin.species.index', [
            'species' => Species::query()->withCount('breeds')->orderBy('name')->get(),
            'animalCounts' => $animalCounts,
        ]);
    }

    public function create(): View
    {
        return view('admin.species.form', ['species' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(), $this->messages());
        $validated['code'] = $this->uniqueCode($validated['name']);

        Species::query()->create($validated);

        return to_route('admin.species.index')->with('success', 'La especie se registró correctamente.');
    }

    public function edit(Species $species): View
    {
        return view('admin.species.form', ['species' => $species]);
    }

    public function update(Request $request, Species $species): RedirectResponse
    {
        $species->update($request->validate($this->rules($species), $this->messages()));

        return to_route('admin.species.index')->with('success', 'La especie se actualizó correctamente.');
    }

    public function destroy(Species $species): RedirectResponse
    {
        if (Animal::query()->withoutGlobalScopes()->withTrashed()->where('species', $species->code)->exists()) {
            return back()->with('error', 'No se puede eliminar la especie porque tiene animales registrados.');
        }

        if ($species->breeds()->exists()) {
            return back()->with('error', 'Elimina primero las razas de esta especie.');
        }

        $species->delete();

        return to_route('admin.species.index')->with('success', 'La especie se eliminó correctamente.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Species $species = null): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('species', 'name')->ignore($species)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre de la especie.',
            'name.unique' => 'Ya existe una especie con ese nombre.',
        ];
    }

    /**
     * El código interno se genera una vez y no cambia, porque los animales lo guardan.
     */
    private function uniqueCode(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 24, '') ?: 'especie';
        $code = $base;
        $suffix = 2;

        while (Species::query()->where('code', $code)->exists()) {
            $code = "{$base}_{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
