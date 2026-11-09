@extends('layouts.app')

@section('title', 'Notificaciones')

@section('content')
@php
    $kindStyles = [
        'vaccination' => ['icon' => '💉', 'tone' => 'blue', 'label' => 'Vacunación'],
        'deworming' => ['icon' => '🪱', 'tone' => 'violet', 'label' => 'Desparasitación'],
        'treatment' => ['icon' => '💊', 'tone' => 'red', 'label' => 'Tratamiento'],
        'health_check' => ['icon' => '🩺', 'tone' => 'teal', 'label' => 'Control veterinario'],
        'weighing' => ['icon' => '⚖️', 'tone' => 'amber', 'label' => 'Pesaje'],
        'calving' => ['icon' => '🐄', 'tone' => 'green', 'label' => 'Parto'],
        'medication_expiry' => ['icon' => '⏳', 'tone' => 'red', 'label' => 'Medicamento'],
        'supply_expiry' => ['icon' => '📦', 'tone' => 'amber', 'label' => 'Insumo'],
        'reminder' => ['icon' => '📅', 'tone' => 'blue', 'label' => 'Calendario'],
    ];
    $leadOptions = [0 => 'El mismo día', 1 => '1 día antes', 3 => '3 días antes', 7 => '1 semana antes', 14 => '2 semanas antes'];
    $today = \Carbon\CarbonImmutable::today();
    $groupedNotifications = $notifications->getCollection()->groupBy(function ($notification) use ($today) {
        $created = \Carbon\CarbonImmutable::parse($notification->created_at)->startOfDay();
        if ($created->equalTo($today)) {
            return 'Hoy';
        }
        if ($created->equalTo($today->subDay())) {
            return 'Ayer';
        }
        if ($created->greaterThanOrEqualTo($today->subDays(6))) {
            return 'Esta semana';
        }

        return 'Anteriores';
    });
@endphp

<div class="nt-hero">
    <div class="nt-hero-text">
        <div class="eyebrow">Seguimiento de la finca</div>
        <h1>Notificaciones</h1>
        <p class="page-subtitle">Avisos de vacunas, partos, pesajes y tareas próximas de tus fincas.</p>
    </div>
    <div class="nt-hero-stats">
        <div class="nt-stat {{ $newCount > 0 ? 'is-highlight' : '' }}">
            <span class="nt-stat-value">{{ $newCount }}</span>
            <span class="nt-stat-label">{{ $newCount === 1 ? 'Nueva' : 'Nuevas' }}</span>
        </div>
        <div class="nt-stat">
            <span class="nt-stat-value">{{ $totalCount }}</span>
            <span class="nt-stat-label">En total</span>
        </div>
        <div class="nt-stat">
            <span class="nt-stat-value nt-stat-state {{ $preference->enabled ? 'is-on' : 'is-off' }}">{{ $preference->enabled ? 'Activas' : 'Pausadas' }}</span>
            <span class="nt-stat-label">Aviso {{ mb_strtolower($leadOptions[$preference->lead_days] ?? $preference->lead_days.' días antes') }}</span>
        </div>
    </div>
</div>

<div class="nt-layout">
    <section class="nt-feed" aria-label="Historial de notificaciones">
        <div class="nt-feed-head">
            <h2>Historial</h2>
            @if ($notifications->isNotEmpty())
                <div class="nt-filter" role="group" aria-label="Filtrar notificaciones">
                    <button type="button" class="is-active" data-nt-filter="all">Todas</button>
                    <button type="button" data-nt-filter="new" @disabled($newCount === 0)>Nuevas @if ($newCount > 0)<b>{{ $newCount }}</b>@endif</button>
                </div>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div class="nt-empty">
                <div class="nt-empty-icon" aria-hidden="true">🔔</div>
                <strong>Todo al día</strong>
                <p>Aún no tienes avisos. Cuando se acerque una vacuna, un parto o una tarea del calendario, aparecerá aquí y en tu dispositivo.</p>
            </div>
        @else
            @foreach ($groupedNotifications as $groupLabel => $items)
                <div class="nt-group" data-nt-group>
                    <div class="nt-group-label">{{ $groupLabel }}</div>
                    <ol class="nt-list">
                        @foreach ($items as $notification)
                            @php
                                $data = $notification->data;
                                $style = $kindStyles[$data['kind'] ?? ''] ?? ['icon' => '🔔', 'tone' => 'green', 'label' => 'Recordatorio'];
                                $dueDate = filled($data['date'] ?? null) ? \Carbon\CarbonImmutable::parse($data['date'])->startOfDay() : null;
                                $daysLeft = $dueDate ? (int) $today->diffInDays($dueDate, false) : null;
                                $dueLabel = match (true) {
                                    $daysLeft === null => null,
                                    $daysLeft < 0 => 'Venció hace '.abs($daysLeft).' '.(abs($daysLeft) === 1 ? 'día' : 'días'),
                                    $daysLeft === 0 => 'Es hoy',
                                    $daysLeft === 1 => 'Mañana',
                                    default => 'En '.$daysLeft.' días',
                                };
                                $dueTone = match (true) {
                                    $daysLeft === null => '',
                                    $daysLeft <= 0 => 'is-urgent',
                                    $daysLeft <= 2 => 'is-soon',
                                    default => '',
                                };
                                $isNew = ! $notification->read_at;
                                $detailPath = filled($data['url'] ?? null) ? parse_url((string) $data['url'], PHP_URL_PATH) : null;
                            @endphp
                            <li @class(['nt-card', 'tone-'.$style['tone'], 'is-new' => $isNew]) data-nt-item data-nt-new="{{ $isNew ? '1' : '0' }}">
                                <span class="nt-card-icon" aria-hidden="true">{{ $style['icon'] }}</span>
                                <div class="nt-card-body">
                                    <div class="nt-card-top">
                                        <span class="nt-card-kind">{{ $style['label'] }}</span>
                                        @if ($isNew)<span class="nt-new-pill">Nueva</span>@endif
                                        <time class="nt-card-time" datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->format('d/m/Y H:i') }}">{{ $notification->created_at->diffForHumans() }}</time>
                                    </div>
                                    <strong class="nt-card-title">{{ $data['title'] ?? 'Recordatorio ganadero' }}</strong>
                                    @if (filled($data['body'] ?? null))<p class="nt-card-text">{{ $data['body'] }}</p>@endif
                                    <div class="nt-card-meta">
                                        @if ($dueLabel)<span class="nt-due {{ $dueTone }}">⏰ {{ $dueLabel }} · {{ $dueDate->format('d/m/Y') }}</span>@endif
                                        @if (filled($data['animal_name'] ?? null))<span class="nt-chip">🏷️ {{ $data['animal_name'] }}</span>@endif
                                        @if (is_string($detailPath) && str_starts_with($detailPath, '/'))
                                            <a class="nt-card-link" href="{{ $detailPath }}">Ver detalle →</a>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
            <div class="nt-filter-empty" data-nt-filter-empty hidden>No hay notificaciones nuevas en esta página.</div>
            <div class="pagination">{{ $notifications->links() }}</div>
        @endif
    </section>

    <aside class="nt-settings">
        <div class="nt-card-panel nt-device">
            <div class="nt-panel-head">
                <span class="nt-panel-icon" aria-hidden="true">📱</span>
                <div>
                    <h2>Este dispositivo</h2>
                    <p data-push-status>Recibe los avisos en este celular o computador aunque la app esté cerrada.</p>
                </div>
            </div>
            <button class="nt-switch-row" type="button" role="switch" aria-checked="false" data-push-toggle data-push-public-key="{{ config('services.notifications.vapid_public_key') }}">
                <span class="nt-switch-text" data-push-label>Activar avisos en este dispositivo</span>
                <span class="nt-switch" aria-hidden="true"><span></span></span>
            </button>
        </div>

        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="nt-card-panel nt-preferences" data-nt-preferences>
            @csrf
            @method('PUT')
            <div class="nt-panel-head">
                <span class="nt-panel-icon" aria-hidden="true">⚙️</span>
                <div>
                    <h2>Preferencias</h2>
                    <p>Elige cuándo y sobre qué quieres que te avisemos.</p>
                </div>
            </div>

            <label class="nt-switch-row">
                <span class="nt-switch-text"><strong>Recibir notificaciones</strong><small>Pausa todos los avisos sin perder tu configuración.</small></span>
                <input class="nt-switch-input" type="checkbox" name="enabled" value="1" @checked($preference->enabled) data-nt-master>
                <span class="nt-switch" aria-hidden="true"><span></span></span>
            </label>

            <div class="nt-preference-body" data-nt-dependent>
                <fieldset class="nt-fieldset">
                    <legend>¿Con cuánta anticipación?</legend>
                    <div class="nt-segmented">
                        @foreach ($leadOptions as $value => $label)
                            <label>
                                <input type="radio" name="lead_days" value="{{ $value }}" @checked((int) $preference->lead_days === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="nt-fieldset">
                    <legend>
                        Tipos de aviso
                        <span class="nt-quick">
                            <button type="button" data-nt-select="all">Todos</button>
                            <button type="button" data-nt-select="none">Ninguno</button>
                        </span>
                    </legend>
                    <div class="nt-categories">
                        @foreach ($categories as $value => $label)
                            @php
                                $categoryStyle = $kindStyles[$value] ?? ['icon' => '🔔', 'tone' => 'green'];
                            @endphp
                            <label class="nt-category tone-{{ $categoryStyle['tone'] }}">
                                <input type="checkbox" name="categories[]" value="{{ $value }}" @checked($preference->categories === null || in_array($value, $preference->categories, true)) data-nt-category>
                                <span class="nt-category-icon" aria-hidden="true">{{ $categoryStyle['icon'] }}</span>
                                <span class="nt-category-label">{{ $label }}</span>
                                <span class="nt-switch nt-switch-small" aria-hidden="true"><span></span></span>
                            </label>
                        @endforeach
                    </div>
                    <p class="nt-hint" data-nt-none-hint hidden>Si no eliges ningún tipo, recibirás todos.</p>
                </fieldset>
            </div>

            <button class="button button-primary nt-save" type="submit">Guardar preferencias</button>
        </form>
    </aside>
</div>
@endsection
