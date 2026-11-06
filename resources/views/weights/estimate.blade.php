@extends('layouts.app')

@section('title', 'Estimador de peso bovino')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('weights.index') }}">Pesajes y crecimiento</a> / Estimador</div>
            <h1>Estimador de peso bovino</h1>
            <p class="page-subtitle">Obtén una referencia aproximada con las medidas de una cinta métrica.</p>
        </div>
    </div>

    <section class="form-panel">
        <div class="empty-state">
            <strong>Cómo tomar las medidas</strong>
            Mide el perímetro torácico justo detrás de las patas delanteras y el largo corporal desde la punta del hombro hasta el isquion. Puedes ingresar las medidas en centímetros (180) o metros (1,80); el sistema las convierte automáticamente.
        </div>

        <form method="POST" action="{{ route('weights.estimate.calculate') }}">
            @csrf
            <div class="field-grid">
                <div class="field">
                    <label for="heart_girth_cm">Perímetro torácico (cm o m) *</label>
                    <input class="control" id="heart_girth_cm" name="heart_girth_cm" type="text" inputmode="decimal" placeholder="Ej.: 180 o 1,80" value="{{ old('heart_girth_cm', $measurements['heart_girth_cm'] ?? '') }}" required>
                    @error('heart_girth_cm')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="body_length_cm">Largo corporal (cm o m) *</label>
                    <input class="control" id="body_length_cm" name="body_length_cm" type="text" inputmode="decimal" placeholder="Ej.: 150 o 1,50" value="{{ old('body_length_cm', $measurements['body_length_cm'] ?? '') }}" required>
                    @error('body_length_cm')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('weights.index') }}">Volver a pesajes</a>
                <button class="button button-primary" type="submit">Calcular estimado</button>
            </div>
        </form>

        @if ($estimatedWeightKg !== null)
            <section class="import-report" aria-live="polite">
                <div class="eyebrow">Resultado aproximado</div>
                <h2>{{ number_format($estimatedWeightKg, 0, ',', '.') }} kg</h2>
                <p>Estimación calculada con perímetro torácico de {{ number_format((float) $measurements['heart_girth_cm'], 1, ',', '.') }} cm y largo corporal de {{ number_format((float) $measurements['body_length_cm'], 1, ',', '.') }} cm.</p>
                <p>Es una referencia, no sustituye el pesaje en báscula. La raza, condición corporal, llenado digestivo y estado de gestación pueden cambiar el resultado. Este cálculo no se guarda en el historial como un pesaje medido.</p>
            </section>
        @endif
    </section>
@endsection
