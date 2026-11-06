@extends('layouts.app')

@section('title', $eventTypes[$event->event_type] . ' · ' . $event->animal->ear_tag)

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('reproduction.index') }}">Reproducción</a> / Detalle de evento</div>
            <h1>{{ $eventTypes[$event->event_type] }}</h1>
            <p class="page-subtitle">{{ $event->event_date->format('d/m/Y') }} · <a class="animal-link" href="{{ route('animals.show', $event->animal) }}">{{ $event->animal->ear_tag }}{{ $event->animal->name ? ' · ' . $event->animal->name : '' }}</a></p>
        </div>
        <a class="button button-secondary" href="{{ route('reproduction.create', ['animal_id' => $event->animal_id]) }}">Registrar otro evento</a>
    </div>

    <div class="reproduction-detail-grid">
        <section class="detail-panel">
            <div class="detail-title">
                <div><h2>Detalle del evento</h2><p>Registro reproductivo asociado a la vaca.</p></div>
                <span class="tag">{{ $eventTypes[$event->event_type] }}</span>
            </div>
            <dl class="detail-list">
                <div class="detail-item"><dt>Finca</dt><dd>{{ $event->animal->farm?->name ?? 'Sin finca asignada' }}</dd></div>
                @if (in_array($event->event_type, ['natural_mating', 'artificial_insemination'], true))
                    <div class="detail-item"><dt>Semental</dt><dd>{{ $event->sireAnimal?->ear_tag ?? $event->sire_name ?? 'No especificado' }}</dd></div>
                @endif
                @if ($event->semen_code)
                    <div class="detail-item"><dt>Código de pajilla / dosis</dt><dd>{{ $event->semen_code }}</dd></div>
                @endif
                @if ($event->technician)
                    <div class="detail-item"><dt>Técnico inseminador</dt><dd>{{ $event->technician }}</dd></div>
                @endif
                @if ($event->diagnosis_result)
                    <div class="detail-item"><dt>Resultado</dt><dd>{{ $event->diagnosis_result === 'pregnant' ? 'Preñada' : 'No preñada' }}</dd></div>
                @endif
                @if ($event->expected_calving_date)
                    <div class="detail-item"><dt>Fecha probable de parto</dt><dd>{{ $event->expected_calving_date->format('d/m/Y') }}</dd></div>
                @endif
                @if ($event->gestation_weeks)
                    <div class="detail-item"><dt>Gestación estimada</dt><dd>{{ $event->gestation_weeks }} semanas</dd></div>
                @endif
                @if ($event->calving_difficulty)
                    <div class="detail-item"><dt>Tipo de parto</dt><dd>{{ ['normal' => 'Normal', 'assisted' => 'Asistido', 'cesarean' => 'Cesárea'][$event->calving_difficulty] }}</dd></div>
                @endif
                @if ($event->notes)
                    <div class="detail-item reproduction-notes"><dt>Observaciones</dt><dd>{{ $event->notes }}</dd></div>
                @endif
            </dl>
        </section>
    </div>

    @if ($event->event_type === 'calving')
        <div class="section-heading farm-animals-heading">
            <div>
                <h2>Terneros nacidos</h2>
                <p>Fichas creadas y vinculadas a la madre.</p>
            </div>
            <span class="tag tag-active">{{ $event->calves->count() }} {{ $event->calves->count() === 1 ? 'ternero' : 'terneros' }}</span>
        </div>
        <section class="table-wrap" aria-label="Terneros del parto">
            <table class="data-table">
                <thead>
                    <tr><th>Identificación</th><th>Sexo</th><th>Fecha de nacimiento</th><th>Peso al nacer</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($event->calves as $calf)
                        <tr>
                            <td><a class="animal-link" href="{{ route('animals.show', $calf) }}">{{ $calf->ear_tag }}</a><span class="animal-sub">{{ $calf->name ?: 'Sin nombre' }}</span></td>
                            <td>{{ $calf->sex === 'female' ? 'Hembra' : 'Macho' }}</td>
                            <td>{{ $calf->birth_date?->format('d/m/Y') }}</td>
                            <td>{{ $calf->birth_weight_kg ? $calf->birth_weight_kg . ' kg' : '—' }}</td>
                            <td><a class="button button-secondary button-small" href="{{ route('animals.show', $calf) }}">Ver ficha</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
