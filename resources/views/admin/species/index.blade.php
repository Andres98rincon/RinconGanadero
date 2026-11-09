@extends('layouts.app')

@section('title', 'Especies')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Catálogos del sistema</div>
            <h1>Especies</h1>
            <p class="page-subtitle">Especies que se pueden registrar en el inventario. Cada especie agrupa sus propias razas.</p>
        </div>
        <div class="detail-actions">
            <a class="button button-secondary" href="{{ route('admin.breeds.index') }}">Ver razas</a>
            <a class="button button-primary" href="{{ route('admin.species.create') }}"><span aria-hidden="true">+</span> Nueva especie</a>
        </div>
    </div>

    <section class="table-wrap" aria-label="Listado de especies">
        @if ($species->isEmpty())
            <div class="empty-state"><strong>No hay especies registradas.</strong>Agrega la primera especie para empezar.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Especie</th><th>Razas</th><th>Animales</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach ($species as $item)
                        @php($animals = (int) ($animalCounts[$item->code] ?? 0))
                        <tr>
                            <td><strong>{{ $item->name }}</strong><span class="animal-sub">{{ $item->description ?: 'Código interno: '.$item->code }}</span></td>
                            <td><a href="{{ route('admin.breeds.index', ['species_id' => $item->id]) }}">{{ number_format($item->breeds_count) }} {{ $item->breeds_count === 1 ? 'raza' : 'razas' }}</a></td>
                            <td>{{ number_format($animals) }}</td>
                            <td>
                                <div class="detail-actions">
                                    <a class="button button-secondary button-small" href="{{ route('admin.breeds.create', ['species_id' => $item->id]) }}">Agregar raza</a>
                                    <a class="button button-secondary button-small" href="{{ route('admin.species.edit', $item) }}">Editar</a>
                                    @if ($animals === 0 && $item->breeds_count === 0)
                                        <form method="POST" action="{{ route('admin.species.destroy', $item) }}" data-confirm="¿Eliminar la especie {{ $item->name }}?">
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
        @endif
    </section>
    <div class="role-notice permission-note">Una especie solo se puede eliminar cuando no tiene razas ni animales registrados.</div>
@endsection
