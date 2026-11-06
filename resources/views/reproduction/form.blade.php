@extends('layouts.app')

@section('title', 'Registrar evento reproductivo')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('reproduction.index') }}">Reproducción</a> / Nuevo evento</div>
            <h1>Registrar evento reproductivo</h1>
            <p class="page-subtitle">Registra el evento en su fecha real; el historial y el estado de la vaca se actualizarán.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('reproduction.store') }}" data-reproductive-form>
        @csrf
        <div class="form-layout">
            <div class="form-panel">
                <div class="section-heading">
                    <h2>Evento y animal</h2>
                    <p>Este registro es exclusivo para vacas bovinas activas.</p>
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label for="animal_id">Vaca *</label>
                        <select class="control" id="animal_id" name="animal_id" required>
                            <option value="">Seleccionar vaca</option>
                            @foreach ($cows as $cow)
                                <option value="{{ $cow->id }}" @selected((string) old('animal_id', $selectedCowId ?? '') === (string) $cow->id)>{{ $cow->ear_tag }}{{ $cow->name ? ' · ' . $cow->name : '' }}</option>
                            @endforeach
                        </select>
                        @error('animal_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="event_type">Tipo de evento *</label>
                        <select class="control" id="event_type" name="event_type" data-reproductive-type required>
                            <option value="">Seleccionar evento</option>
                            @foreach ($eventTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('event_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('event_type')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="event_date">Fecha del evento *</label>
                        <input class="control" id="event_date" name="event_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('event_date', today()->toDateString()) }}" required>
                        @error('event_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="reproductive-section" data-reproductive-for="natural_mating artificial_insemination calving" hidden>
                    <div class="section-heading">
                        <h2>Semental relacionado</h2>
                        <p>Selecciona un toro del inventario o escribe el nombre del semental externo.</p>
                    </div>
                    <div class="field-grid">
                        <div class="field">
                            <label for="sire_animal_id">Toro del inventario</label>
                            <select class="control" id="sire_animal_id" name="sire_animal_id">
                                <option value="">No seleccionado</option>
                                @foreach ($bulls as $bull)
                                    <option value="{{ $bull->id }}" @selected((string) old('sire_animal_id') === (string) $bull->id)>{{ $bull->ear_tag }}{{ $bull->name ? ' · ' . $bull->name : '' }}</option>
                                @endforeach
                            </select>
                            @error('sire_animal_id')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="sire_name">Nombre / identificación externa</label>
                            <input class="control" id="sire_name" name="sire_name" value="{{ old('sire_name') }}" maxlength="150">
                            @error('sire_name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field" data-reproductive-for="artificial_insemination">
                            <label for="semen_code">Código de pajilla / dosis</label>
                            <input class="control" id="semen_code" name="semen_code" value="{{ old('semen_code') }}" maxlength="100">
                            @error('semen_code')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field" data-reproductive-for="artificial_insemination">
                            <label for="technician">Técnico inseminador</label>
                            <input class="control" id="technician" name="technician" value="{{ old('technician') }}" maxlength="150">
                            @error('technician')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="reproductive-section" data-reproductive-for="pregnancy_diagnosis" hidden>
                    <div class="section-heading">
                        <h2>Diagnóstico de preñez</h2>
                        <p>Registra el resultado confirmado y la fecha probable de parto.</p>
                    </div>
                    <div class="field-grid">
                        <div class="field">
                            <label for="diagnosis_result">Resultado *</label>
                            <select class="control" id="diagnosis_result" name="diagnosis_result">
                                <option value="">Seleccionar resultado</option>
                                <option value="pregnant" @selected(old('diagnosis_result') === 'pregnant')>Preñada</option>
                                <option value="not_pregnant" @selected(old('diagnosis_result') === 'not_pregnant')>No preñada</option>
                            </select>
                            @error('diagnosis_result')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="expected_calving_date">Fecha probable de parto</label>
                            <input class="control" id="expected_calving_date" name="expected_calving_date" type="date" value="{{ old('expected_calving_date') }}">
                            @error('expected_calving_date')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="reproductive-section" data-reproductive-for="abortion" hidden>
                    <div class="section-heading">
                        <h2>Registro de aborto</h2>
                        <p>El evento quedará en el historial y actualizará el estado reproductivo de la vaca.</p>
                    </div>
                    <div class="field-grid">
                        <div class="field">
                            <label for="gestation_weeks">Semanas de gestación estimadas</label>
                            <input class="control" id="gestation_weeks" name="gestation_weeks" type="number" min="1" max="45" value="{{ old('gestation_weeks') }}">
                            @error('gestation_weeks')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="reproductive-section" data-reproductive-for="calving" hidden>
                    <div class="section-heading">
                        <h2>Parto y terneros</h2>
                        <p>Se creará una ficha por cada ternero y quedará vinculada a su madre y a este parto.</p>
                    </div>
                    <div class="field-grid">
                        <div class="field">
                            <label for="calving_difficulty">Tipo de parto *</label>
                            <select class="control" id="calving_difficulty" name="calving_difficulty">
                                <option value="">Seleccionar</option>
                                <option value="normal" @selected(old('calving_difficulty') === 'normal')>Normal</option>
                                <option value="assisted" @selected(old('calving_difficulty') === 'assisted')>Asistido</option>
                                <option value="cesarean" @selected(old('calving_difficulty') === 'cesarean')>Cesárea</option>
                            </select>
                            @error('calving_difficulty')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="calf-list" data-calf-list>
                        @foreach (old('calves') ?: [['ear_tag' => '', 'name' => '', 'sex' => '', 'birth_weight_kg' => '']] as $index => $calfInput)
                            <div class="calf-row" data-calf-row>
                                <div class="field">
                                    <label>Arete / identificación *</label>
                                    <input class="control" name="calves[{{ $index }}][ear_tag]" maxlength="40" value="{{ $calfInput['ear_tag'] ?? '' }}">
                                    @error('calves.' . $index . '.ear_tag')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Nombre</label>
                                    <input class="control" name="calves[{{ $index }}][name]" maxlength="100" value="{{ $calfInput['name'] ?? '' }}">
                                </div>
                                <div class="field">
                                    <label>Sexo *</label>
                                    <select class="control" name="calves[{{ $index }}][sex]">
                                        <option value="">Seleccionar</option>
                                        <option value="female" @selected(($calfInput['sex'] ?? '') === 'female')>Hembra</option>
                                        <option value="male" @selected(($calfInput['sex'] ?? '') === 'male')>Macho</option>
                                    </select>
                                    @error('calves.' . $index . '.sex')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Peso al nacer (kg)</label>
                                    <input class="control" name="calves[{{ $index }}][birth_weight_kg]" type="number" step="0.01" min="0" value="{{ $calfInput['birth_weight_kg'] ?? '' }}">
                                    @error('calves.' . $index . '.birth_weight_kg')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                                <button class="event-delete calf-remove" type="button" data-remove-calf>Quitar ternero</button>
                            </div>
                        @endforeach
                        @error('calves')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <button class="button button-secondary" type="button" data-add-calf>+ Añadir ternero</button>
                    <template data-calf-template>
                        <div class="calf-row" data-calf-row>
                            <div class="field">
                                <label>Arete / identificación *</label>
                                <input class="control" name="calves[__INDEX__][ear_tag]" maxlength="40">
                            </div>
                            <div class="field">
                                <label>Nombre</label>
                                <input class="control" name="calves[__INDEX__][name]" maxlength="100">
                            </div>
                            <div class="field">
                                <label>Sexo *</label>
                                <select class="control" name="calves[__INDEX__][sex]">
                                    <option value="">Seleccionar</option>
                                    <option value="female">Hembra</option>
                                    <option value="male">Macho</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Peso al nacer (kg)</label>
                                <input class="control" name="calves[__INDEX__][birth_weight_kg]" type="number" step="0.01" min="0">
                            </div>
                            <button class="event-delete calf-remove" type="button" data-remove-calf>Quitar ternero</button>
                        </div>
                    </template>
                </div>

                <div class="section-heading">
                    <h2>Observaciones</h2>
                    <p>Agrega síntomas, hallazgos, tratamientos o cualquier detalle útil para el seguimiento.</p>
                </div>
                <div class="field-grid">
                    <div class="field field-full">
                        <label for="notes">Notas del evento</label>
                        <textarea class="control" id="notes" name="notes" maxlength="10000">{{ old('notes') }}</textarea>
                        @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ route('reproduction.index') }}">Cancelar</a>
                    <button class="button button-primary" type="submit">Guardar evento</button>
                </div>
            </div>
            <aside class="side-panel">
                <h3>Historial confiable</h3>
                <p>Cada evento queda asociado a la ficha de la vaca. Los servicios, diagnósticos y partos actualizan su estado reproductivo.</p>
                <ul>
                    <li>La fecha probable se registra en el diagnóstico; no se calcula automáticamente.</li>
                    <li>Los partos crean automáticamente las fichas de los terneros.</li>
                    <li>Los eventos guardados conservan su historial.</li>
                </ul>
            </aside>
        </div>
    </form>
@endsection
