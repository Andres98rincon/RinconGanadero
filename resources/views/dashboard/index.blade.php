@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
@php
    $farmId = $filters['farm_id'] ?? '';
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->trim()->explode(' ')->first();
    $hour = (int) now('America/Bogota')->format('G');
    $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
    $selectedFarm = $farms->firstWhere('id', (int) $farmId);
    $milkUp = $stats['milk_today'] >= $stats['milk_yesterday'];
    $milkDifference = $stats['milk_today'] - $stats['milk_yesterday'];
    $milkMaximum = max(1, (float) ($milkTrend['maximum'] ?? 0));
    $femaleCount = (int) ($sexCounts->get('female')->animal_count ?? 0);
    $maleCount = (int) ($sexCounts->get('male')->animal_count ?? 0);
    $otherCount = (int) ($sexCounts->get('unknown')->animal_count ?? 0);
@endphp

<div class="db">
    <header class="db-head">
        <div class="db-head-text">
            <span class="db-date">{{ ucfirst(now()->locale('es')->translatedFormat('l, d \d\e F \d\e Y')) }}</span>
            <p class="db-greeting">{{ $greeting }}, {{ $firstName }} 👋</p>
            <h1>{{ __('dashboard.headline') }}</h1>
            <p class="db-subtitle">{{ __('dashboard.subtitle') }}</p>
        </div>
        <form class="db-farm-filter" method="GET" action="{{ route('dashboard') }}">
            <label for="dashboard-farm">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>
                <span>{{ __('dashboard.farm_view') }}</span>
            </label>
            <select class="control" id="dashboard-farm" name="farm_id" onchange="this.form.submit()" data-no-select2>
                <option value="">{{ __('dashboard.all_farms') }}</option>
                @foreach ($farms as $farm)
                    <option value="{{ $farm->id }}" @selected((string) $farmId === (string) $farm->id)>{{ $farm->name }}</option>
                @endforeach
            </select>
            <noscript><button class="button button-secondary" type="submit">{{ __('dashboard.apply') }}</button></noscript>
        </form>
    </header>

    <section class="db-hero" aria-label="Bienvenida">
        <div class="db-hero-content">
            <span class="db-hero-kicker"><span class="db-live-dot"></span> {{ $selectedFarm?->name ?? 'Todas tus fincas' }}</span>
            <h2>Un hato sano.<br><em>Una finca que crece.</em></h2>
            <p>Tu operación ganadera organizada en un solo lugar: animales, leche, reproducción y finanzas al día.</p>
            <div class="db-hero-metrics">
                <div><strong>{{ number_format($stats['active_animals']) }}</strong><span>Animales activos</span></div>
                <div><strong>{{ number_format($stats['milk_today'], 1, ',', '.') }} <small>L</small></strong><span>Leche hoy</span></div>
                <div><strong>{{ number_format($stats['pregnant']) }}</strong><span>Preñadas</span></div>
            </div>
            <div class="db-hero-actions">
                <a class="db-btn db-btn-light" href="{{ route('animals.create', ['farm_id' => $farmId]) }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Registrar animal
                </a>
                <a class="db-btn db-btn-ghost" href="{{ route('reports.index') }}">Ver reportes <span aria-hidden="true">→</span></a>
            </div>
        </div>
        <figure class="db-hero-photo">
            <img src="{{ asset('images/Domino.jpeg') }}" alt="Dominó, ternero del hato, echado sobre el pasto con su jáquima roja" loading="eager">
            <figcaption class="db-hero-badge">
                <span class="db-hero-badge-icon" aria-hidden="true">★</span>
                <span><strong>Dominó</strong><small>Orgullo del hato</small></span>
            </figcaption>
        </figure>
    </section>

    <section class="db-kpis" aria-label="Indicadores clave">
        <a class="db-kpi db-tone-green" href="{{ route('animals.index', ['farm_id' => $farmId]) }}">
            <div class="db-kpi-top">
                <span class="db-kpi-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8 4 5 2 7l2 5v4a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-4l2-5-2-2-3 3z"/><path d="M9 12h.01M15 12h.01M9 16h6"/></svg></span>
                <span class="db-pill">+{{ number_format($stats['births']) }} nacimientos este mes</span>
            </div>
            <strong class="db-kpi-value">{{ number_format($stats['active_animals']) }}</strong>
            <span class="db-kpi-label">Animales en la finca</span>
            <span class="db-kpi-foot">{{ number_format($stats['cattle']) }} bovinos · {{ number_format($stats['females']) }} hembras</span>
            <span class="db-kpi-foot">{{ number_format($stats['average_weight'], 1, ',', '.') }} kg de peso promedio</span>
        </a>
        <a class="db-kpi db-tone-blue" href="{{ route('milk-production.index', ['farm_id' => $farmId]) }}">
            <div class="db-kpi-top">
                <span class="db-kpi-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5S5 10 5 15a7 7 0 0 0 14 0c0-5-7-12.5-7-12.5Z"/><path d="M9 16a3 3 0 0 0 3 3"/></svg></span>
                @if ($stats['milk_yesterday'] > 0)
                    <span class="db-pill {{ $milkUp ? 'is-up' : 'is-down' }}">{{ $milkUp ? '▲' : '▼' }} {{ number_format(abs($milkDifference), 1, ',', '.') }} L vs. ayer</span>
                @else
                    <span class="db-pill">Registro diario</span>
                @endif
            </div>
            <strong class="db-kpi-value">{{ number_format($stats['milk_today'], 1, ',', '.') }} <small>L</small></strong>
            <span class="db-kpi-label">Leche producida hoy</span>
            <span class="db-kpi-foot">{{ number_format($stats['milk_month'], 0, ',', '.') }} L este mes</span>
        </a>
        <a class="db-kpi db-tone-amber" href="{{ route('reproduction.index') }}">
            <div class="db-kpi-top">
                <span class="db-kpi-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/></svg></span>
                <span class="db-pill">Vencidos o próximos</span>
            </div>
            <strong class="db-kpi-value">{{ number_format($stats['calvings_due']) }}</strong>
            <span class="db-kpi-label">Partos por atender</span>
            <span class="db-kpi-foot">{{ number_format($stats['pregnant']) }} vacas registradas como preñadas</span>
        </a>
        <a class="db-kpi db-tone-violet" href="{{ route('finance.index') }}">
            <div class="db-kpi-top">
                <span class="db-kpi-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                <span class="db-pill {{ $stats['month_balance'] >= 0 ? 'is-up' : 'is-down' }}">{{ $stats['month_balance'] >= 0 ? 'Positivo' : 'Negativo' }}</span>
            </div>
            <strong class="db-kpi-value">${{ number_format($stats['month_balance'], 0, ',', '.') }}</strong>
            <span class="db-kpi-label">Balance del mes</span>
            <span class="db-kpi-foot">${{ number_format($stats['month_income'], 0, ',', '.') }} ingresos · ${{ number_format($stats['month_expenses'], 0, ',', '.') }} gastos</span>
        </a>
    </section>

    <div class="db-grid db-grid-charts">
        <section class="db-card" aria-labelledby="milk-chart-title">
            <div class="db-card-head">
                <div class="db-card-title">
                    <span class="db-card-icon db-tone-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span>
                    <div><h3 id="milk-chart-title">Producción de leche</h3><p>Litros registrados en los últimos 14 días</p></div>
                </div>
                <a class="db-link" href="{{ route('milk-production.index') }}">Ver producción →</a>
            </div>
            <div class="db-chart-summary">
                <div><strong>{{ number_format($milkTrend['total'], 1, ',', '.') }} <small>L</small></strong><span>Total en 14 días</span></div>
                <span class="db-pill {{ $milkTrend['comparison'] >= 0 ? 'is-up' : 'is-down' }}">{{ $milkTrend['comparison'] >= 0 ? '▲ +' : '▼ ' }}{{ number_format($milkTrend['comparison'], 1, ',', '.') }} L vs. semana anterior</span>
            </div>
            <div class="db-line-chart">
                <svg viewBox="0 0 760 220" role="img" aria-labelledby="milk-svg-title milk-svg-description" preserveAspectRatio="none">
                    <title id="milk-svg-title">Tendencia diaria de producción de leche</title>
                    <desc id="milk-svg-description">Gráfico de línea de los litros registrados en cada uno de los últimos catorce días.</desc>
                    <defs>
                        <linearGradient id="milk-area-gradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#3b82c4" stop-opacity=".32"/><stop offset="100%" stop-color="#3b82c4" stop-opacity="0"/></linearGradient>
                        <linearGradient id="milk-line-gradient" x1="0" x2="1" y1="0" y2="0"><stop offset="0%" stop-color="#2f9e74"/><stop offset="100%" stop-color="#3b82c4"/></linearGradient>
                    </defs>
                    <line class="db-gridline" x1="42" y1="39" x2="707" y2="39"/>
                    <line class="db-gridline" x1="42" y1="112" x2="707" y2="112"/>
                    <line class="db-gridline" x1="42" y1="184" x2="707" y2="184"/>
                    <polygon class="db-chart-area" points="{{ $milkTrend['area'] }}"/>
                    <polyline class="db-chart-line" points="{{ $milkTrend['line'] }}"/>
                    @foreach ($milkTrend['points'] as $point)
                        <circle class="db-chart-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4"><title>{{ $point['label'] }}: {{ number_format($point['value'], 1, ',', '.') }} litros</title></circle>
                    @endforeach
                </svg>
                <span class="db-chart-max">Máx. {{ number_format($milkMaximum, 1, ',', '.') }} L</span>
                <div class="db-chart-labels">@foreach ($milkTrend['points'] as $point)<span>{{ $point['label'] }}</span>@endforeach</div>
            </div>
        </section>

        <section class="db-card" aria-labelledby="finance-chart-title">
            <div class="db-card-head">
                <div class="db-card-title">
                    <span class="db-card-icon db-tone-violet"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></span>
                    <div><h3 id="finance-chart-title">Ingresos y gastos</h3><p>Resumen de los últimos 6 meses</p></div>
                </div>
                <a class="db-link" href="{{ route('finance.index') }}">Ver finanzas →</a>
            </div>
            <div class="db-legend"><span><i class="db-dot db-dot-income"></i>Ingresos</span><span><i class="db-dot db-dot-expense"></i>Gastos</span></div>
            <div class="db-bar-chart" role="img" aria-label="Comparación mensual de ingresos y gastos">
                @foreach ($financeTrend as $month)
                    <div class="db-bar-column">
                        <div class="db-bars">
                            <span class="db-bar db-bar-income" style="--h: {{ $month['income_height'] }}%" title="Ingresos {{ $month['label'] }}: ${{ number_format($month['income'], 0, ',', '.') }}"></span>
                            <span class="db-bar db-bar-expense" style="--h: {{ $month['expense_height'] }}%" title="Gastos {{ $month['label'] }}: ${{ number_format($month['expenses'], 0, ',', '.') }}"></span>
                        </div>
                        <span class="db-bar-label">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="db-finance-total">
                <span>Neto de este mes</span>
                <strong class="{{ $stats['month_balance'] >= 0 ? 'is-up' : 'is-down' }}">${{ number_format($stats['month_balance'], 2, ',', '.') }}</strong>
            </div>
        </section>
    </div>

    <div class="db-grid db-grid-content">
        <section class="db-card" aria-labelledby="herd-title">
            <div class="db-card-head">
                <div class="db-card-title">
                    <span class="db-card-icon db-tone-green"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l6 4"/></svg></span>
                    <div><h3 id="herd-title">Tu hato, por especie</h3><p>{{ number_format($stats['active_animals']) }} animales activos</p></div>
                </div>
                <a class="db-link" href="{{ route('animals.index', ['farm_id' => $farmId]) }}">Inventario →</a>
            </div>
            @if ($speciesCounts->isEmpty())
                <div class="db-empty"><span aria-hidden="true">🐄</span>Registra tus animales y aquí podrás ver la composición de tu hato.</div>
            @else
                <div class="db-species">
                    @foreach ($speciesCounts as $index => $species)
                        <div class="db-species-row">
                            <div class="db-species-info">
                                <span class="db-dot db-series-{{ $index % 5 }}"></span>
                                <span class="db-species-name">{{ $species['label'] }}</span>
                                <strong>{{ number_format($species['count']) }}</strong>
                                <small>{{ $species['percentage'] }}%</small>
                            </div>
                            <span class="db-track"><span class="db-progress db-series-{{ $index % 5 }}" style="--w: {{ $species['percentage'] }}%"></span></span>
                        </div>
                    @endforeach
                </div>
                <div class="db-sex-split">
                    <div><span class="db-dot db-dot-female"></span><strong>{{ number_format($femaleCount) }}</strong><small>Hembras</small></div>
                    <div><span class="db-dot db-dot-male"></span><strong>{{ number_format($maleCount) }}</strong><small>Machos</small></div>
                    <div><span class="db-dot db-dot-other"></span><strong>{{ number_format($otherCount) }}</strong><small>Otros</small></div>
                </div>
            @endif
        </section>

        <section class="db-card" aria-labelledby="top-milk-title">
            <div class="db-card-head">
                <div class="db-card-title">
                    <span class="db-card-icon db-tone-amber"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg></span>
                    <div><h3 id="top-milk-title">Vacas destacadas</h3><p>Mayor producción en los últimos 30 días</p></div>
                </div>
                <a class="db-link" href="{{ route('milk-production.index') }}">Ranking →</a>
            </div>
            @if ($topMilkProducers === [])
                <div class="db-empty"><span aria-hidden="true">🥛</span>Cuando registres ordeños, aquí verás las vacas con mayor producción.</div>
            @else
                <ol class="db-list">
                    @foreach ($topMilkProducers as $index => $cow)
                        <li>
                            <a class="db-row" href="{{ route('animals.production.index', $cow->id) }}">
                                <span class="db-rank db-rank-{{ min($index, 3) }}">{{ $index + 1 }}</span>
                                <span class="db-avatar">{{ mb_strtoupper(mb_substr($cow->name ?: $cow->ear_tag, 0, 1)) }}</span>
                                <span class="db-row-main"><strong>{{ $cow->name ?: $cow->ear_tag }}</strong><small>{{ $cow->name ? $cow->ear_tag : 'Registro individual' }}</small></span>
                                <span class="db-row-value">{{ number_format((float) $cow->total_liters, 0, ',', '.') }} <small>L</small></span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="db-card" aria-labelledby="calving-title">
            <div class="db-card-head">
                <div class="db-card-title">
                    <span class="db-card-icon db-tone-rose"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span>
                    <div><h3 id="calving-title">Calendario de partos</h3><p>Vencidos o esperados dentro de 30 días</p></div>
                </div>
                <a class="db-link" href="{{ route('reproduction.index') }}">Reproducción →</a>
            </div>
            @if ($upcomingCalvings->isEmpty())
                <div class="db-empty"><span aria-hidden="true">📅</span>No hay partos previstos para los próximos 30 días.</div>
            @else
                <ol class="db-list">
                    @foreach ($upcomingCalvings as $cow)
                        @php
                            $calvingDate = \Illuminate\Support\Carbon::parse($cow->expected_calving_date);
                            $daysUntil = (int) today()->diffInDays($calvingDate, false);
                        @endphp
                        <li>
                            <a class="db-row" href="{{ route('animals.show', $cow->id) }}">
                                <span class="db-date-chip"><strong>{{ $calvingDate->format('d') }}</strong><small>{{ mb_strtolower($calvingDate->locale('es')->translatedFormat('M')) }}</small></span>
                                <span class="db-row-main"><strong>{{ $cow->ear_tag }}</strong><small>{{ $cow->name ?: 'Sin nombre' }}</small></span>
                                <span class="db-badge {{ $daysUntil < 0 ? 'is-danger' : ($daysUntil <= 7 ? 'is-warning' : '') }}">{{ $daysUntil < 0 ? 'Vencido' : ($daysUntil === 0 ? 'Hoy' : 'En '.$daysUntil.' días') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="db-card" aria-labelledby="activity-title">
            <div class="db-card-head">
                <div class="db-card-title">
                    <span class="db-card-icon db-tone-teal"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></span>
                    <div><h3 id="activity-title">Actividad reciente</h3><p>Últimos registros de tu operación</p></div>
                </div>
                <a class="db-link" href="{{ route('animal-movements.index') }}">Movimientos →</a>
            </div>
            @if ($recentActivity === [])
                <div class="db-empty"><span aria-hidden="true">🕒</span>Aquí aparecerán los últimos eventos reproductivos, compras, ventas y traslados.</div>
            @else
                <ol class="db-timeline">
                    @foreach ($recentActivity as $activity)
                        <li>
                            <a class="db-row" href="{{ $activity['url'] }}">
                                <span class="db-activity-icon db-activity-{{ $activity['type'] }}">{{ $activity['type'] === 'reproduction' ? '♡' : ($activity['type'] === 'purchase' ? '+' : '↗') }}</span>
                                <span class="db-row-main"><strong>{{ $activity['title'] }}</strong><small>{{ $activity['detail'] }}</small></span>
                                <time datetime="{{ $activity['date']->toDateString() }}">{{ $activity['date']->format('d/m') }}</time>
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <section class="db-actions" aria-label="Accesos rápidos">
        <h2 class="db-section-title">Accesos rápidos</h2>
        <div class="db-actions-grid">
            <a class="db-action db-tone-blue" href="{{ route('milk-production.create') }}"><span class="db-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5S5 10 5 15a7 7 0 0 0 14 0c0-5-7-12.5-7-12.5Z"/></svg></span><span><strong>Registrar ordeño</strong><small>Producción diaria</small></span></a>
            <a class="db-action db-tone-rose" href="{{ route('reproduction.create') }}"><span class="db-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/></svg></span><span><strong>Evento reproductivo</strong><small>Celos, servicios y partos</small></span></a>
            <a class="db-action db-tone-amber" href="{{ route('weights.create') }}"><span class="db-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M5 7h14M7 7l-4 8a4 4 0 0 0 8 0zM17 7l-4 8a4 4 0 0 0 8 0z"/></svg></span><span><strong>Registrar pesaje</strong><small>Crecimiento del hato</small></span></a>
            <a class="db-action db-tone-green" href="{{ route('finance.transactions.create') }}"><span class="db-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><span><strong>Nuevo movimiento</strong><small>Ingresos y gastos</small></span></a>
        </div>
    </section>

    <p class="db-footnote">Los indicadores se calculan con los registros actuales del sistema y la finca seleccionada.</p>
</div>
@endsection
