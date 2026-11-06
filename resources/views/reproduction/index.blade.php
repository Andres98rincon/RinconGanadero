@extends('layouts.app')

@section('title', 'Reproducción')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Manejo del hato</div>
            <h1>Reproducción</h1>
            <p class="page-subtitle">Celos, servicios, diagnósticos, partos y nacimientos de las vacas.</p>
        </div>
        <a class="button button-primary" href="{{ route('reproduction.create') }}"><span aria-hidden="true">+</span> Registrar evento</a>
    </div>

    <section class="stats-grid" aria-label="Resumen reproductivo">
        <div class="stat"><span class="stat-label">Vacas registradas</span><strong class="stat-value">{{ number_format($stats['cows']) }}</strong></div>
        <div class="stat"><span class="stat-label">Preñadas</span><strong class="stat-value">{{ number_format($stats['pregnant']) }}</strong></div>
        <div class="stat"><span class="stat-label">Partos vencidos / próximos</span><strong class="stat-value">{{ number_format($stats['due_soon']) }}</strong></div>
        <div class="stat"><span class="stat-label">Eventos este mes</span><strong class="stat-value">{{ number_format($stats['events_this_month']) }}</strong></div>
    </section>

    @if ($upcomingCalvings->isNotEmpty())
        <section class="upcoming-panel">
            <div class="section-heading">
                <h2>Próximos partos</h2>
                <p>Vacas con parto vencido o esperado dentro de los próximos 30 días.</p>
            </div>
            <div class="upcoming-list">
                @foreach ($upcomingCalvings as $cow)
                    <a class="upcoming-item" href="{{ route('animals.show', $cow) }}">
                        <span><strong>{{ $cow->ear_tag }}</strong>{{ $cow->name ? ' · ' . $cow->name : '' }}</span>
                        <span class="tag tag-warning">{{ $cow->expected_calving_date->lessThan(today()) ? 'Vencido · ' : '' }}{{ $cow->expected_calving_date->format('d/m/Y') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <form class="filter-bar" method="GET" action="{{ route('reproduction.index') }}">
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por arete o nombre de la vaca" aria-label="Buscar vacas">
        <select class="filter-control" name="event_type" aria-label="Filtrar por tipo de evento">
            <option value="">Todos los eventos</option>
            @foreach ($eventTypes as $value => $label)
                <option value="{{ $value }}" @selected(($filters['event_type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="button button-secondary" type="submit">Filtrar</button>
        @if ($filters)
            <a class="button button-secondary" href="{{ route('reproduction.index') }}">Limpiar</a>
        @endif
    </form>

    <section class="table-wrap" aria-label="Historial reproductivo">
        @if ($events->isEmpty())
            <div class="empty-state">
                <strong>{{ $filters ? 'No encontramos eventos con esos filtros.' : 'Todavía no hay eventos reproductivos.' }}</strong>
                {{ $filters ? 'Prueba con otros datos de búsqueda.' : 'Registra el primer celo, servicio o diagnóstico para iniciar el historial.' }}
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr><th>Fecha</th><th>Evento</th><th>Vaca</th><th>Semental</th><th>Resultado / detalle</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>{{ $event->event_date->format('d/m/Y') }}</td>
                            <td><span class="tag">{{ $eventTypes[$event->event_type] }}</span></td>
                            <td><a class="animal-link" href="{{ route('animals.show', $event->animal) }}">{{ $event->animal->ear_tag }}</a><span class="animal-sub">{{ $event->animal->name ?: 'Sin nombre' }}</span></td>
                            <td>{{ $event->sireAnimal?->ear_tag ?? $event->sire_name ?? $event->semen_code ?? '—' }}</td>
                            <td>
                                @if ($event->diagnosis_result === 'pregnant')
                                    Preñada · parto probable {{ $event->expected_calving_date?->format('d/m/Y') }}
                                @elseif ($event->diagnosis_result === 'not_pregnant')
                                    No preñada
                                @elseif ($event->event_type === 'calving')
                                    {{ $event->calves_count }} {{ $event->calves_count === 1 ? 'ternero' : 'terneros' }}
                                @elseif ($event->event_type === 'artificial_insemination' && $event->semen_code)
                                    Dosis {{ $event->semen_code }}
                                @else
                                    {{ \Illuminate\Support\Str::limit($event->notes, 55) ?: '—' }}
                                @endif
                            </td>
                            <td><a class="button button-secondary button-small" href="{{ route('reproduction.show', $event) }}">Ver</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $events->firstItem() }}–{{ $events->lastItem() }} de {{ $events->total() }}</span>
                {{ $events->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
