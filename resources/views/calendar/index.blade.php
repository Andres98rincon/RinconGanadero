@extends('layouts.app')

@section('title', 'Calendario ganadero')

@section('content')
@php
    $monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $weekdayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $eventColors = ['vaccination' => 'calendar-vaccination', 'deworming' => 'calendar-deworming', 'treatment' => 'calendar-treatment', 'health_check' => 'calendar-check', 'weighing' => 'calendar-weighing', 'calving' => 'calendar-calving', 'reminder' => 'calendar-reminder'];
@endphp
<section class="page-heading calendar-heading">
    <div><span class="eyebrow">Planificación de la finca</span><h1>Calendario ganadero</h1><p>Vacunaciones, tratamientos, partos y controles próximos en un solo lugar.</p></div>
</section>

<section class="calendar-layout">
    <div class="calendar-main panel">
        <div class="calendar-toolbar">
            <div class="calendar-nav">
                <a class="button button-secondary button-small" href="{{ route('calendar.index', ['view' => $viewMode, 'date' => $previous]) }}" aria-label="Periodo anterior">‹</a>
                <a class="button button-secondary button-small" href="{{ route('calendar.index', ['view' => $viewMode, 'date' => today()->toDateString()]) }}">Hoy</a>
                <a class="button button-secondary button-small" href="{{ route('calendar.index', ['view' => $viewMode, 'date' => $next]) }}" aria-label="Periodo siguiente">›</a>
            </div>
            <h2>{{ $viewMode === 'day' ? $date->format('d/m/Y') : ($viewMode === 'week' ? $start->format('d/m') . ' – ' . $end->format('d/m/Y') : ucfirst($monthNames[$date->month - 1]) . ' ' . $date->year) }}</h2>
            <div class="calendar-view-switch" aria-label="Vista del calendario">
                @foreach (['day' => 'Día', 'week' => 'Semana', 'month' => 'Mes'] as $mode => $label)
                    <a class="{{ $viewMode === $mode ? 'active' : '' }}" href="{{ route('calendar.index', ['view' => $mode, 'date' => $date->toDateString()]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($viewMode === 'month')
            <div class="calendar-grid calendar-month-grid">
                @foreach ($weekdayNames as $weekday)<div class="calendar-weekday">{{ $weekday }}</div>@endforeach
                @foreach ($days as $day)
                    <div class="calendar-cell {{ $day->month !== $date->month ? 'outside-month' : '' }} {{ $day->isToday() ? 'is-today' : '' }}">
                        <span class="calendar-day-number">{{ $day->day }}</span>
                        <div class="calendar-day-events">
                            @foreach ($eventsByDate->get($day->toDateString(), collect())->take(3) as $event)
                                <button class="calendar-event {{ $eventColors[$event['kind']] ?? 'calendar-reminder' }}" type="button"
                                    data-calendar-event data-title="{{ $event['title'] }}" data-date="{{ $event['date'] }}" data-kind="{{ $event['kind_label'] }}"
                                    data-description="{{ $event['description'] }}" data-farm="{{ $event['farm_name'] }}" data-animal="{{ $event['animal_name'] }}"
                                    data-tag="{{ $event['animal_tag'] }}" data-animal-url="{{ $event['animal_url'] }}">
                                    <span>{{ $event['title'] }}</span>
                                </button>
                            @endforeach
                            @if ($eventsByDate->get($day->toDateString(), collect())->count() > 3)
                                <span class="calendar-more">+{{ $eventsByDate->get($day->toDateString())->count() - 3 }} más</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($viewMode === 'week')
            <div class="calendar-grid calendar-week-grid">
                @foreach ($days as $day)
                    <section class="calendar-week-column {{ $day->isToday() ? 'is-today' : '' }}">
                        <h3>{{ $weekdayNames[$day->dayOfWeekIso - 1] }} <span>{{ $day->format('d/m') }}</span></h3>
                        @forelse ($eventsByDate->get($day->toDateString(), collect()) as $event)
                            <button class="calendar-event calendar-event-card {{ $eventColors[$event['kind']] ?? 'calendar-reminder' }}" type="button"
                                data-calendar-event data-title="{{ $event['title'] }}" data-date="{{ $event['date'] }}" data-kind="{{ $event['kind_label'] }}"
                                data-description="{{ $event['description'] }}" data-farm="{{ $event['farm_name'] }}" data-animal="{{ $event['animal_name'] }}"
                                data-tag="{{ $event['animal_tag'] }}" data-animal-url="{{ $event['animal_url'] }}">
                                <strong>{{ $event['title'] }}</strong><small>{{ $event['kind_label'] }}</small>
                            </button>
                        @empty <p class="calendar-empty-day">Sin eventos</p>
                        @endforelse
                    </section>
                @endforeach
            </div>
        @else
            <div class="calendar-agenda">
                @forelse ($events as $event)
                    <button class="calendar-agenda-event {{ $eventColors[$event['kind']] ?? 'calendar-reminder' }}" type="button"
                        data-calendar-event data-title="{{ $event['title'] }}" data-date="{{ $event['date'] }}" data-kind="{{ $event['kind_label'] }}"
                        data-description="{{ $event['description'] }}" data-farm="{{ $event['farm_name'] }}" data-animal="{{ $event['animal_name'] }}"
                        data-tag="{{ $event['animal_tag'] }}" data-animal-url="{{ $event['animal_url'] }}">
                        <span class="calendar-agenda-date">{{ \Carbon\CarbonImmutable::parse($event['date'])->format('d/m') }}</span>
                        <span class="calendar-agenda-copy"><strong>{{ $event['title'] }}</strong><small>{{ $event['kind_label'] }} · {{ $event['farm_name'] }}</small></span>
                        <span aria-hidden="true">›</span>
                    </button>
                @empty
                    <div class="empty-state"><strong>No hay eventos para este día</strong>Programa controles desde la ficha de un animal o crea un recordatorio.</div>
                @endforelse
            </div>
        @endif
        <div class="calendar-legend">
            @foreach (['calendar-vaccination' => 'Vacunación', 'calendar-treatment' => 'Tratamiento', 'calendar-check' => 'Control veterinario', 'calendar-weighing' => 'Pesaje', 'calendar-calving' => 'Parto', 'calendar-reminder' => 'Recordatorio'] as $color => $label)
                <span><i class="{{ $color }}"></i>{{ $label }}</span>
            @endforeach
        </div>
    </div>

    @can('animals.update')
    <aside class="calendar-sidebar panel">
        <span class="eyebrow">Planifica</span><h2>Nuevo recordatorio</h2>
        <form method="POST" action="{{ route('calendar.reminders.store') }}" class="calendar-reminder-form">
            @csrf
            <label for="reminder-title">Título *</label><input class="control" id="reminder-title" name="title" maxlength="150" value="{{ old('title') }}" required placeholder="Ej. Revisar cerca norte">
            <label for="reminder-date">Fecha *</label><input class="control" id="reminder-date" name="event_date" type="date" value="{{ old('event_date', $date->toDateString()) }}" required>
            <label for="reminder-farm">Finca *</label><select class="control" id="reminder-farm" name="farm_id" required>
                <option value="">Selecciona una finca</option>
                @foreach ($farms as $farm)<option value="{{ $farm->id }}" @selected(old('farm_id') == $farm->id)>{{ $farm->name }}</option>@endforeach
            </select>
            <label for="reminder-animal">Animal <span class="optional-label">Opcional</span></label><select class="control" id="reminder-animal" name="animal_id">
                <option value="">Sin animal asociado</option>
                @foreach ($animals as $animal)<option value="{{ $animal->id }}" data-farm="{{ $animal->farm_id }}" @selected(old('animal_id') == $animal->id)>{{ $animal->ear_tag }} · {{ $animal->name ?: 'Sin nombre' }}</option>@endforeach
            </select>
            <label for="reminder-description">Detalle</label><textarea class="control" id="reminder-description" name="description" rows="3" maxlength="5000" placeholder="Información útil para el equipo">{{ old('description') }}</textarea>
            <button class="button button-primary" type="submit">Guardar recordatorio</button>
        </form>
        <div class="calendar-upcoming"><h3>Eventos del periodo</h3><strong>{{ $events->count() }}</strong><span>{{ $viewMode === 'month' ? 'en este mes' : ($viewMode === 'week' ? 'esta semana' : 'para este día') }}</span></div>
    </aside>
    @endcan
</section>

<dialog class="calendar-event-dialog" data-calendar-dialog>
    <form method="dialog"><button class="calendar-dialog-close" aria-label="Cerrar">×</button></form>
    <span class="eyebrow" data-dialog-kind></span><h2 data-dialog-title></h2>
    <dl class="calendar-dialog-details"><div><dt>Fecha</dt><dd data-dialog-date></dd></div><div><dt>Finca</dt><dd data-dialog-farm></dd></div><div data-dialog-animal-row><dt>Animal</dt><dd data-dialog-animal></dd></div></dl>
    <p data-dialog-description></p><a class="button button-primary" data-dialog-animal-link href="#">Abrir ficha del animal</a>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('[data-calendar-dialog]');
    document.querySelectorAll('[data-calendar-event]').forEach((button) => button.addEventListener('click', () => {
        dialog.querySelector('[data-dialog-kind]').textContent = button.dataset.kind || 'Evento';
        dialog.querySelector('[data-dialog-title]').textContent = button.dataset.title || '';
        dialog.querySelector('[data-dialog-date]').textContent = new Date(`${button.dataset.date}T00:00:00`).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });
        dialog.querySelector('[data-dialog-farm]').textContent = button.dataset.farm || '';
        dialog.querySelector('[data-dialog-animal]').textContent = button.dataset.animal ? `${button.dataset.animal} · ${button.dataset.tag}` : 'No aplica';
        dialog.querySelector('[data-dialog-description]').textContent = button.dataset.description || 'Sin detalles adicionales.';
        dialog.querySelector('[data-dialog-animal-row]').hidden = !button.dataset.animal;
        const link = dialog.querySelector('[data-dialog-animal-link]');
        link.href = button.dataset.animalUrl || '#';
        link.hidden = !button.dataset.animalUrl;
        dialog.showModal();
    }));

    const farm = document.querySelector('#reminder-farm');
    const animal = document.querySelector('#reminder-animal');
    if (farm && animal) {
        const updateAnimals = () => {
            for (const option of animal.options) {
                if (!option.value) continue;
                const visible = option.dataset.farm === farm.value;
                option.disabled = !visible;
                if (!visible && option.selected) animal.value = '';
            }
        };
        farm.addEventListener('change', updateAnimals);
        updateAnimals();
    }
});
</script>
@endsection
