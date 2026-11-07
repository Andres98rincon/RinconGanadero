@extends('layouts.app')

@section('title', 'Ficha ' . $animal->ear_tag)

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('animals.index') }}">Inventario</a> / Ficha individual</div>
            <h1>{{ $animal->name ?: $animal->ear_tag }}</h1>
            <p class="page-subtitle">Identificación {{ $animal->ear_tag }} · {{ \App\Models\Animal::SPECIES[$animal->species] ?? 'Otro' }} · {{ $animal->breed?->name ?? 'Raza sin especificar' }}</p>
        </div>

    </div>
     <div class="detail-actions">
            @if ($animal->status === 'active')
                <a class="button button-secondary" href="{{ route('animal-movements.create', ['animal_id' => $animal->id, 'movement_type' => 'sale']) }}">Registrar venta o traslado</a>
            @endif
            <a class="button button-secondary" href="{{ route('weights.index', ['animal_id' => $animal->id]) }}">Ver crecimiento</a>
            <a class="button button-secondary" href="{{ route('weights.create', ['animal_id' => $animal->id]) }}">Registrar pesaje</a>
            <a class="button button-secondary" href="{{ route('animals.edit', $animal) }}">Editar ficha</a>
            <form method="POST" action="{{ route('animals.destroy', $animal) }}" data-confirm="Retirar este animal del inventario? Su historial quedara archivado.">
                @csrf
                @method('DELETE')
                <button class="button button-danger" type="submit">Retirar</button>
            </form>
        </div>

        <br>
    <div class="detail-grid">
        <section class="detail-panel">
            @if ($animal->hasMedia('photo'))
                <img class="animal-detail-photo" src="{{ $animal->getFirstMedia('photo')?->getAvailableUrl(['optimized']) }}" alt="Foto de {{ $animal->ear_tag }}">
            @endif
            <div class="detail-title">
                <div><h2>Ficha del animal</h2><p>Resumen de identificacion, origen y seguimiento.</p></div>
                <span class="tag {{ $animal->status === 'active' ? 'tag-active' : 'tag-muted' }}">{{ \App\Models\Animal::STATUSES[$animal->status] ?? $animal->status }}</span>
            </div>
            <dl class="detail-list">
                <div class="detail-item"><dt>Sexo</dt><dd>{{ ['female' => 'Hembra', 'male' => 'Macho', 'unknown' => 'Sin definir'][$animal->sex] ?? 'Sin definir' }}</dd></div>
                <div class="detail-item"><dt>Nacimiento / edad</dt><dd>{{ $animal->birth_date?->format('d/m/Y') ?? 'Sin fecha' }}{{ $animal->formatted_age ? ' · ' . $animal->formatted_age : '' }}</dd></div>
                <div class="detail-item"><dt>Microchip</dt><dd>{{ $animal->microchip ?: 'No registrado' }}</dd></div>
                <div class="detail-item"><dt>Registro genealógico</dt><dd>{{ $animal->registration_number ?: 'No registrado' }}</dd></div>
                <div class="detail-item"><dt>Peso al nacer</dt><dd>{{ $animal->birth_weight_kg ? $animal->birth_weight_kg . ' kg' : 'Sin dato' }}</dd></div>
                <div class="detail-item"><dt>Peso actual</dt><dd>{{ $animal->current_weight_kg ? $animal->current_weight_kg . ' kg' : 'Sin dato' }}{{ $animal->last_weighed_at ? ' · ' . $animal->last_weighed_at->format('d/m/Y') : '' }}</dd></div>
                <div class="detail-item"><dt>Madre</dt><dd>{{ $animal->dam?->ear_tag ?? 'No registrada' }}</dd></div>
                <div class="detail-item"><dt>Padre</dt><dd>{{ $animal->sire?->ear_tag ?? 'No registrado' }}</dd></div>
                <div class="detail-item"><dt>Ingreso</dt><dd>{{ ['born' => 'Nacido en finca', 'purchased' => 'Comprado', 'donated' => 'Donado', 'transferred' => 'Trasladado', 'unknown' => 'Desconocido'][$animal->acquisition_type] ?? 'Desconocido' }}{{ $animal->acquisition_date ? ' · ' . $animal->acquisition_date->format('d/m/Y') : '' }}</dd></div>
                <div class="detail-item"><dt>Propósito</dt><dd>{{ ['dairy' => 'Leche', 'meat' => 'Carne', 'breeding' => 'Reproducción', 'dual' => 'Doble propósito', 'work' => 'Trabajo', 'other' => 'Otro'][$animal->purpose] ?? 'Sin especificar' }}</dd></div>
                <div class="detail-item"><dt>Estado reproductivo</dt><dd>{{ ['open' => 'Abierta', 'served' => 'Servida, pendiente de diagnóstico', 'pregnant' => 'Preñada', 'lactating' => 'Lactando', 'dry' => 'Seca', 'not_applicable' => 'No aplica'][$animal->reproductive_status] ?? 'Sin dato' }}{{ $animal->expected_calving_date ? ' · Parto probable ' . $animal->expected_calving_date->format('d/m/Y') : '' }}</dd></div>
                <div class="detail-item"><dt>Finca</dt><dd>@if ($animal->farm)<a class="animal-link" href="{{ route('farms.show', $animal->farm) }}">{{ $animal->farm->name }} · {{ $animal->farm->code }}</a>@else Sin finca asignada @endif</dd></div>
                <div class="detail-item"><dt>Potrero / corral</dt><dd>{{ $animal->paddock?->name ?? ($animal->location ?: 'Sin asignar') }}</dd></div>
                <div class="detail-item"><dt>Color / marcas</dt><dd>{{ trim(($animal->color ?? '') . ' ' . ($animal->markings ?? '')) ?: 'Sin dato' }}</dd></div>
                @if ($animal->supplier_name)
                    <div class="detail-item"><dt>Procedencia</dt><dd>{{ $animal->supplier_name }}</dd></div>
                @endif
                @if ($animal->health_notes)
                    <div class="detail-item"><dt>Antecedentes de salud</dt><dd>{{ $animal->health_notes }}</dd></div>
                @endif
                @if ($animal->notes)
                    <div class="detail-item"><dt>Notas</dt><dd>{{ $animal->notes }}</dd></div>
                @endif
            </dl>

            <section class="animal-qr-card" aria-labelledby="animal-qr-title">
                <div class="detail-title">
                    <div><h2 id="animal-qr-title">Código QR individual</h2><p>Escanéalo para abrir esta ficha con una cuenta autorizada.</p></div>
                </div>
                <img class="animal-qr-image" src="{{ route('animals.qr.image', $animal) }}" alt="Código QR para abrir la ficha de {{ $animal->ear_tag }}" width="240" height="240">
                <p class="animal-qr-code"><span>Código manual</span><strong>{{ $animal->qr_code }}</strong></p>
                <a class="button button-secondary" href="{{ route('animals.qr.image', $animal) }}" download="animal-{{ $animal->qr_code }}.svg">Descargar QR</a>
            </section>
        </section>

        <section class="history-panel">
            <h2>Bitácora de campo</h2>
            <form class="event-form" method="POST" action="{{ route('animals.events.store', $animal) }}" data-offline-form="animal-event" data-offline-animal-id="{{ $animal->id }}">
                @csrf
                <div class="field">
                    <label for="event_type">Tipo</label>
                    <select class="control" id="event_type" name="event_type" required>
                        @foreach (\App\Http\Controllers\AnimalEventController::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('event_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="event_date">Fecha</label>
                    <input class="control" id="event_date" name="event_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('event_date', now()->toDateString()) }}" required>
                </div>
                <div class="field field-full">
                    <label for="title">Descripcion breve</label>
                    <input class="control" id="title" name="title" value="{{ old('title') }}" maxlength="150" required>
                    @error('title')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="weight_kg">Peso (kg)</label>
                    <input class="control" id="weight_kg" name="weight_kg" type="number" step="0.01" min="0" value="{{ old('weight_kg') }}">
                    @error('weight_kg')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="cost">Costo</label>
                    <input class="control" id="cost" name="cost" type="number" step="0.01" min="0" value="{{ old('cost') }}">
                    @error('cost')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field field-full">
                    <label for="due_date">Próxima fecha de control <span>Opcional</span></label>
                    <input class="control" id="due_date" name="due_date" type="date" value="{{ old('due_date') }}">
                    @error('due_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field field-full">
                    <label for="description">Detalle / producto aplicado</label>
                    <textarea class="control" id="description" name="description" maxlength="10000">{{ old('description') }}</textarea>
                    @error('description')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <button class="button button-primary" type="submit">Agregar a la bitacora</button>
            </form>

            @forelse ($events as $event)
                <article class="event-item">
                    <div class="event-date">{{ $event->event_date->format('d/m/Y') }} · {{ \App\Http\Controllers\AnimalEventController::TYPES[$event->event_type] ?? 'Evento' }}{{ $event->due_date ? ' · Proximo control: ' . $event->due_date->format('d/m/Y') : '' }}</div>
                    <h3 class="event-title">{{ $event->title }}</h3>
                    @if ($event->description)<p class="event-description">{{ $event->description }}</p>@endif
                    @if ($event->weight_kg || $event->cost)
                        <div class="event-meta">{{ $event->weight_kg ? $event->weight_kg . ' kg' : '' }}{{ $event->weight_kg && $event->cost ? ' · ' : '' }}{{ $event->cost ? '$' . number_format((float) $event->cost, 2) : '' }}</div>
                    @endif
                    <form method="POST" action="{{ route('animals.events.destroy', [$animal, $event]) }}" data-confirm="Eliminar este evento del historial?">
                        @csrf
                        @method('DELETE')
                        <button class="event-delete" type="submit">Eliminar evento</button>
                    </form>
                </article>
            @empty
                <p class="event-description">Aun no hay eventos registrados para este animal.</p>
            @endforelse
            {{ $events->links() }}
        </section>
    </div>

    @if ($animal->species === 'bovine' && $animal->sex === 'female')
        <section class="reproductive-history-panel">
            <div class="section-heading farm-animals-heading">
                <div>
                    <h2>Historial reproductivo y productivo</h2>
                    <p>{{ $productionStats['record_count'] }} días de producción · {{ number_format((float) $productionStats['month_total'], 2, ',', '.') }} L este mes</p>
                </div>
                <div class="detail-actions">
                    <a class="button button-secondary" href="{{ route('animals.production.index', $animal) }}">Ver producción</a>
                    <a class="button button-secondary" href="{{ route('reproduction.index', ['q' => $animal->ear_tag]) }}">Ver historial</a>
                    <a class="button button-primary" href="{{ route('reproduction.create', ['animal_id' => $animal->id]) }}">Registrar evento</a>
                </div>
            </div>
            @forelse ($reproductiveEvents as $reproductiveEvent)
                <a class="event-item reproduction-history-item" href="{{ route('reproduction.show', $reproductiveEvent) }}">
                    <div class="event-date">{{ $reproductiveEvent->event_date->format('d/m/Y') }} · {{ \App\Models\ReproductiveEvent::TYPES[$reproductiveEvent->event_type] }}</div>
                    <h3 class="event-title">
                        @if ($reproductiveEvent->diagnosis_result === 'pregnant')
                            Preñada · parto probable {{ $reproductiveEvent->expected_calving_date?->format('d/m/Y') }}
                        @elseif ($reproductiveEvent->diagnosis_result === 'not_pregnant')
                            Diagnóstico negativo
                        @elseif ($reproductiveEvent->event_type === 'calving')
                            {{ $reproductiveEvent->calves->count() }} {{ $reproductiveEvent->calves->count() === 1 ? 'ternero nacido' : 'terneros nacidos' }}
                        @else
                            {{ $reproductiveEvent->sireAnimal?->ear_tag ?? $reproductiveEvent->sire_name ?? $reproductiveEvent->notes ?? 'Evento registrado' }}
                        @endif
                    </h3>
                </a>
            @empty
                <p class="event-description">Todavía no hay eventos reproductivos registrados para esta vaca.</p>
            @endforelse
        </section>
    @endif
        <br>
    <section class="table-wrap" aria-label="Historial de compras, ventas y traslados">
        <div class="farm-animals-heading p-4">
            <div><h2>Historial de compras, ventas y traslados</h2><p>Los movimientos conservan el precio, motivo y ubicación registrada en cada fecha.</p></div>
        </div>
        @if ($movements->isEmpty())
            <div class="empty-state"><strong>Sin movimientos comerciales ni traslados.</strong>Las compras, ventas y cambios de ubicación aparecerán aquí.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Movimiento</th><th>Origen</th><th>Destino</th><th>Precio</th><th>Motivo / contraparte</th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr>
                            <td>{{ $movement->movement_date->format('d/m/Y') }}</td>
                            <td>{{ \App\Models\AnimalMovement::TYPES[$movement->movement_type] }}</td>
                            <td>{{ $movement->fromFarm?->name ?? $movement->from_farm_name ?? ($movement->movement_type === 'purchase' ? 'Proveedor / origen' : '—') }}{{ $movement->from_location ? ' · ' . $movement->from_location : '' }}</td>
                            <td>{{ $movement->toFarm?->name ?? $movement->to_farm_name ?? ($movement->movement_type === 'sale' ? 'Comprador / destino' : '—') }}{{ $movement->to_location ? ' · ' . $movement->to_location : '' }}</td>
                            <td>{{ $movement->amount !== null ? '$' . number_format((float) $movement->amount, 2, ',', '.') : '—' }}</td>
                            <td>{{ $movement->exit_reason ? (\App\Models\AnimalMovement::EXIT_REASONS[$movement->exit_reason] ?? $movement->exit_reason) : ($movement->counterparty_name ?: '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
