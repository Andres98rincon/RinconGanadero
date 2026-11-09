@extends('layouts.app')

@section('title', 'Razas')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.species.index') }}">Especies</a> / Razas</div>
            <h1>Razas</h1>
            <p class="page-subtitle">Cada raza pertenece a una especie y aparece al registrar animales de esa especie.</p>
        </div>
        <div class="detail-actions">
            <a class="button button-primary" href="{{ route('admin.breeds.create', ['species_id' => $filters['species_id'] ?? null]) }}"><span aria-hidden="true">+</span> Nueva raza</a>
        </div>
    </div>

    <form class="filter-bar" method="GET" action="{{ route('admin.breeds.index') }}">
        <select class="filter-control" name="species_id" aria-label="Filtrar por especie">
            <option value="">Todas las especies</option>
            @foreach ($species as $item)
                <option value="{{ $item->id }}" @selected((string) ($filters['species_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar raza" aria-label="Buscar raza">
        <button class="button button-secondary" type="submit">Filtrar</button>
        <a class="button button-secondary" href="{{ route('admin.breeds.index') }}">Limpiar</a>
    </form>

    <section class="table-wrap" aria-label="Listado de razas">
        @if ($breeds->isEmpty())
            <div class="empty-state"><strong>No hay razas con esos filtros.</strong>Agrega una raza para usarla en el inventario.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Raza</th><th>Especie</th><th>Origen</th><th>Animales</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach ($breeds as $breed)
                        @php($animals = (int) ($animalCounts[$breed->id] ?? 0))
                        <tr>
                            <td><strong>{{ $breed->name }}</strong>@if ($breed->description)<span class="animal-sub">{{ \Illuminate\Support\Str::limit($breed->description, 90) }}</span>@endif</td>
                            <td>{{ $breed->speciesCatalog?->name ?? \App\Models\Species::label($breed->species) }}</td>
                            <td>{{ $breed->origin ?: '—' }}</td>
                            <td>{{ number_format($animals) }}</td>
                            <td>
                                <div class="detail-actions">
                                    <a class="button button-secondary button-small" href="{{ route('admin.breeds.edit', $breed) }}">Editar</a>
                                    @if ($animals === 0)
                                        <form method="POST" action="{{ route('admin.breeds.destroy', $breed) }}" data-confirm="¿Eliminar la raza {{ $breed->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger button-small" type="submit">Eliminar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $breeds->firstItem() }}–{{ $breeds->lastItem() }} de {{ $breeds->total() }} razas</span>
                {{ $breeds->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
