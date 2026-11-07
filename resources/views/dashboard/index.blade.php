@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
    <div class="dashboard-head">
        <div>
            <div class="eyebrow">Panel de control · {{ now()->locale('es')->translatedFormat('l, d \d\e F \d\e Y') }}</div>
            <h1>{{ __('dashboard.headline') }}</h1>
            <p class="page-subtitle">{{ __('dashboard.subtitle') }}</p>
        </div>
        <form class="dashboard-farm-filter" method="GET" action="{{ route('dashboard') }}">
            <label for="dashboard-farm">{{ __('dashboard.farm_view') }}</label>
            <select class="control" id="dashboard-farm" name="farm_id">
                <option value="">{{ __('dashboard.all_farms') }}</option>
                @foreach ($farms as $farm)
                    <option value="{{ $farm->id }}" @selected((string) ($filters['farm_id'] ?? '') === (string) $farm->id)>{{ $farm->name }}</option>
                @endforeach
            </select>
            <button class="button button-secondary" type="submit">{{ __('dashboard.apply') }}</button>
        </form>
    </div>

    <section class="dashboard-hero" aria-label="Bienvenida">
        <img class="dashboard-hero-image" src="{{ asset('images/girolando-5-8.jpg') }}" alt="Vaca Girolando 5/8 en pastoreo">
        <div class="dashboard-hero-content">
            <span class="dashboard-hero-kicker"><span class="dashboard-live-dot"></span> RINCÓN GANADERO · GESTIÓN GANADERA</span>
            <h2>Un hato sano.<br>Una finca que crece.</h2>
            <p>Tu operación ganadera, organizada en un solo lugar. Revisa lo importante y sigue adelante.</p>
            <div class="dashboard-hero-actions">
                <a class="dashboard-hero-button" href="{{ route('animals.create', ['farm_id' => $filters['farm_id'] ?? '']) }}">+ Registrar animal</a>
                <a class="dashboard-hero-link" href="{{ route('reports.index') }}">Ver reportes <span aria-hidden="true">→</span></a>
            </div>
            <a class="dashboard-image-credit" href="https://commons.wikimedia.org/wiki/File:Cinco_Oitavos.jpg" target="_blank" rel="noopener noreferrer">Foto: teliocampos · Wikimedia Commons · Dominio público</a>
        </div>
        <div class="dashboard-hero-stamp" aria-label="Ganado Girolando">
            <span>RAZA LECHERA</span>
            <strong>Girolando</strong>
            <small>5/8 Holstein · 3/8 Gir</small>
        </div>
    </section>

    <section class="dashboard-kpis" aria-label="Indicadores clave">
        <a class="dashboard-kpi dashboard-kpi-green" href="{{ route('animals.index', ['farm_id' => $filters['farm_id'] ?? '']) }}">
            <span class="dashboard-kpi-top"><span class="dashboard-kpi-icon">♧</span><span class="dashboard-kpi-trend">{{ number_format($stats['births']) }} nacimientos este mes</span></span>
            <strong>{{ number_format($stats['active_animals']) }}</strong>
            <span class="dashboard-kpi-label">Animales en la finca</span>
            <span class="dashboard-kpi-foot">{{ number_format($stats['cattle']) }} bovinos · {{ number_format($stats['females']) }} hembras</span>
            <span class="dashboard-kpi-foot">{{ number_format($stats['average_weight'], 1, ',', '.') }} kg de peso promedio</span>
        </a>
        <a class="dashboard-kpi dashboard-kpi-blue" href="{{ route('milk-production.index', ['farm_id' => $filters['farm_id'] ?? '']) }}">
            <span class="dashboard-kpi-top"><span class="dashboard-kpi-icon">◉</span><span class="dashboard-kpi-trend">{{ $stats['milk_yesterday'] > 0 ? ($stats['milk_today'] >= $stats['milk_yesterday'] ? '↑' : '↓') . ' vs. ayer' : 'Registro diario' }}</span></span>
            <strong>{{ number_format($stats['milk_today'], 1, ',', '.') }} <small>L</small></strong>
            <span class="dashboard-kpi-label">Leche producida hoy</span>
            <span class="dashboard-kpi-foot">{{ number_format($stats['milk_month'], 0, ',', '.') }} L este mes</span>
        </a>
        <a class="dashboard-kpi dashboard-kpi-amber" href="{{ route('reproduction.index') }}">
            <span class="dashboard-kpi-top"><span class="dashboard-kpi-icon">♡</span><span class="dashboard-kpi-trend">Vencidos o próximos</span></span>
            <strong>{{ number_format($stats['calvings_due']) }}</strong>
            <span class="dashboard-kpi-label">Partos por atender</span>
            <span class="dashboard-kpi-foot">{{ number_format($stats['pregnant']) }} vacas registradas como preñadas</span>
        </a>
        <a class="dashboard-kpi dashboard-kpi-violet" href="{{ route('finance.index') }}">
            <span class="dashboard-kpi-top"><span class="dashboard-kpi-icon">$</span><span class="dashboard-kpi-trend">Balance del mes</span></span>
            <strong>${{ number_format($stats['month_balance'], 0, ',', '.') }}</strong>
            <span class="dashboard-kpi-label">Flujo neto registrado</span>
            <span class="dashboard-kpi-foot">${{ number_format($stats['month_income'], 0, ',', '.') }} ingresos · ${{ number_format($stats['month_expenses'], 0, ',', '.') }} gastos</span>
        </a>
    </section>

    <div class="dashboard-section-title">
        <div><span class="eyebrow">Acompaña el ritmo de tu finca</span><h2>Así va la operación</h2></div>
        <span class="dashboard-period-pill">Actualizado al {{ today()->format('d/m/Y') }}</span>
    </div>

    <div class="dashboard-chart-grid">
        <section class="dashboard-card dashboard-milk-card" aria-labelledby="milk-chart-title">
            <div class="dashboard-card-heading">
                <div><span class="dashboard-card-icon dashboard-card-icon-blue">◉</span><div><h3 id="milk-chart-title">Producción de leche</h3><p>Litros registrados en los últimos 14 días</p></div></div>
                <a class="dashboard-card-link" href="{{ route('milk-production.index') }}">Ver producción <span aria-hidden="true">→</span></a>
            </div>
            <div class="dashboard-chart-summary"><strong>{{ number_format($milkTrend['total'], 1, ',', '.') }} <small>L</small></strong><span>En 14 días</span><span class="dashboard-chart-change {{ $milkTrend['comparison'] >= 0 ? 'positive' : 'negative' }}">{{ $milkTrend['comparison'] >= 0 ? '+' : '' }}{{ number_format($milkTrend['comparison'], 1, ',', '.') }} L <small>vs. semana anterior</small></span></div>
            <div class="dashboard-line-chart">
                <svg viewBox="0 0 760 220" role="img" aria-labelledby="milk-svg-title milk-svg-description" preserveAspectRatio="none">
                    <title id="milk-svg-title">Tendencia diaria de producción de leche</title>
                    <desc id="milk-svg-description">Gráfico de línea de los litros registrados en cada uno de los últimos catorce días.</desc>
                    <defs><linearGradient id="milk-area-gradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#4f9d83" stop-opacity=".24"/><stop offset="100%" stop-color="#4f9d83" stop-opacity="0"/></linearGradient></defs>
                    <line class="dashboard-gridline" x1="42" y1="39" x2="707" y2="39"/>
                    <line class="dashboard-gridline" x1="42" y1="112" x2="707" y2="112"/>
                    <line class="dashboard-gridline" x1="42" y1="184" x2="707" y2="184"/>
                    <polygon class="dashboard-chart-area" points="{{ $milkTrend['area'] }}"/>
                    <polyline class="dashboard-chart-line" points="{{ $milkTrend['line'] }}"/>
                    @foreach ($milkTrend['points'] as $point)
                        <circle class="dashboard-chart-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5"><title>{{ $point['label'] }}: {{ number_format($point['value'], 1, ',', '.') }} litros</title></circle>
                    @endforeach
                </svg>
                <div class="dashboard-chart-labels">@foreach ($milkTrend['points'] as $point)<span>{{ $point['label'] }}</span>@endforeach</div>
            </div>
        </section>

        <section class="dashboard-card dashboard-finance-card" aria-labelledby="finance-chart-title">
            <div class="dashboard-card-heading">
                <div><span class="dashboard-card-icon dashboard-card-icon-orange">$</span><div><h3 id="finance-chart-title">Ingresos y gastos</h3><p>Resumen de los últimos 6 meses</p></div></div>
                <a class="dashboard-card-link" href="{{ route('finance.index') }}">Ver finanzas <span aria-hidden="true">→</span></a>
            </div>
            <div class="dashboard-chart-legend"><span><i class="legend-income"></i>Ingresos</span><span><i class="legend-expenses"></i>Gastos</span></div>
            <div class="dashboard-bar-chart" role="img" aria-label="Comparación mensual de ingresos y gastos">
                @foreach ($financeTrend as $month)
                    <div class="dashboard-bar-column">
                        <div class="dashboard-bars">
                            <span class="dashboard-bar dashboard-bar-income" style="height: {{ $month['income_height'] }}%" title="Ingresos {{ $month['label'] }}: ${{ number_format($month['income'], 0, ',', '.') }}"></span>
                            <span class="dashboard-bar dashboard-bar-expenses" style="height: {{ $month['expense_height'] }}%" title="Gastos {{ $month['label'] }}: ${{ number_format($month['expenses'], 0, ',', '.') }}"></span>
                        </div>
                        <span class="dashboard-bar-label">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="dashboard-finance-total"><span>Neto de este mes</span><strong class="{{ $stats['month_balance'] >= 0 ? 'positive' : 'negative' }}">${{ number_format($stats['month_balance'], 2, ',', '.') }}</strong></div>
        </section>
    </div>

    <div class="dashboard-content-grid">
        <section class="dashboard-card dashboard-herd-card" aria-labelledby="herd-title">
            <div class="dashboard-card-heading">
                <div><span class="dashboard-card-icon dashboard-card-icon-green">♧</span><div><h3 id="herd-title">Tu hato, por especie</h3><p>{{ number_format($stats['active_animals']) }} animales activos</p></div></div>
                <a class="dashboard-card-link" href="{{ route('animals.index', ['farm_id' => $filters['farm_id'] ?? '']) }}">Abrir inventario <span aria-hidden="true">→</span></a>
            </div>
            @if ($speciesCounts->isEmpty())
                <div class="dashboard-empty">Registra tus animales y aquí podrás ver la composición de tu hato.</div>
            @else
                <div class="dashboard-species-list">
                    @foreach ($speciesCounts as $index => $species)
                        <div class="dashboard-species-row">
                            <span class="dashboard-species-dot dashboard-species-dot-{{ $index % 5 }}"></span>
                            <span class="dashboard-species-name">{{ $species['label'] }}</span>
                            <span class="dashboard-species-track"><span class="dashboard-species-progress dashboard-species-progress-{{ $index % 5 }}" style="width: {{ $species['percentage'] }}%"></span></span>
                            <strong>{{ number_format($species['count']) }}</strong>
                            <small>{{ $species['percentage'] }}%</small>
                        </div>
                    @endforeach
                </div>
                <div class="dashboard-herd-foot">
                    <span><i class="sex-female-dot"></i> Hembras <strong>{{ number_format((int) ($sexCounts->get('female')->animal_count ?? 0)) }}</strong></span>
                    <span><i class="sex-male-dot"></i> Machos <strong>{{ number_format((int) ($sexCounts->get('male')->animal_count ?? 0)) }}</strong></span>
                    <span><i class="sex-other-dot"></i> Otros <strong>{{ number_format((int) ($sexCounts->get('unknown')->animal_count ?? 0)) }}</strong></span>
                </div>
            @endif
        </section>

        <section class="dashboard-card dashboard-production-card" aria-labelledby="top-milk-title">
            <div class="dashboard-card-heading">
                <div><span class="dashboard-card-icon dashboard-card-icon-blue">✦</span><div><h3 id="top-milk-title">Vacas destacadas</h3><p>Mayor producción en los últimos 30 días</p></div></div>
                <a class="dashboard-card-link" href="{{ route('milk-production.index') }}">Ver ranking <span aria-hidden="true">→</span></a>
            </div>
            @if ($topMilkProducers === [])
                <div class="dashboard-empty">Cuando registres ordeños, aquí verás las vacas con mayor producción.</div>
            @else
                <div class="dashboard-producer-list">
                    @foreach ($topMilkProducers as $index => $cow)
                        <a class="dashboard-producer-row" href="{{ route('animals.production.index', $cow->id) }}">
                            <span class="dashboard-producer-rank">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="dashboard-producer-avatar">{{ mb_substr($cow->name ?: $cow->ear_tag, 0, 1) }}</span>
                            <span class="dashboard-producer-name"><strong>{{ $cow->name ?: $cow->ear_tag }}</strong><small>{{ $cow->name ? $cow->ear_tag : 'Registro individual' }}</small></span>
                            <span class="dashboard-producer-liters">{{ number_format((float) $cow->total_liters, 0, ',', '.') }} <small>L</small></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="dashboard-card dashboard-calving-card" aria-labelledby="calving-title">
            <div class="dashboard-card-heading">
                <div><span class="dashboard-card-icon dashboard-card-icon-amber">♡</span><div><h3 id="calving-title">Calendario de partos</h3><p>Partos vencidos o esperados dentro de 30 días</p></div></div>
                <a class="dashboard-card-link" href="{{ route('reproduction.index') }}">Ver reproducción <span aria-hidden="true">→</span></a>
            </div>
            @if ($upcomingCalvings->isEmpty())
                <div class="dashboard-empty">No hay partos previstos para los próximos 30 días.</div>
            @else
                <div class="dashboard-calving-list">
                    @foreach ($upcomingCalvings as $cow)
                        @php($calvingDate = \Illuminate\Support\Carbon::parse($cow->expected_calving_date))
                        @php($daysUntil = today()->diffInDays($calvingDate, false))
                        <a class="dashboard-calving-row" href="{{ route('animals.show', $cow->id) }}">
                            <span class="dashboard-calving-date"><strong>{{ $calvingDate->format('d') }}</strong><small>{{ mb_strtolower($calvingDate->locale('es')->translatedFormat('M')) }}</small></span>
                            <span class="dashboard-calving-cow"><strong>{{ $cow->ear_tag }}</strong><small>{{ $cow->name ?: 'Sin nombre' }}</small></span>
                            <span class="dashboard-calving-countdown {{ $daysUntil < 0 ? 'overdue' : '' }}">{{ $daysUntil < 0 ? 'Vencido' : ($daysUntil === 0 ? 'Hoy' : 'En ' . $daysUntil . ' días') }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="dashboard-card dashboard-activity-card" aria-labelledby="activity-title">
            <div class="dashboard-card-heading">
                <div><span class="dashboard-card-icon dashboard-card-icon-green">↻</span><div><h3 id="activity-title">Actividad reciente</h3><p>Últimos registros de tu operación</p></div></div>
                <a class="dashboard-card-link" href="{{ route('animal-movements.index') }}">Ver movimientos <span aria-hidden="true">→</span></a>
            </div>
            @if ($recentActivity === [])
                <div class="dashboard-empty">Aquí aparecerán los últimos eventos reproductivos, compras, ventas y traslados.</div>
            @else
                <div class="dashboard-activity-list">
                    @foreach ($recentActivity as $activity)
                        <a class="dashboard-activity-row" href="{{ $activity['url'] }}">
                            <span class="dashboard-activity-icon dashboard-activity-icon-{{ $activity['type'] }}">{{ $activity['type'] === 'reproduction' ? '♡' : ($activity['type'] === 'purchase' ? '+' : '↗') }}</span>
                            <span class="dashboard-activity-detail"><strong>{{ $activity['title'] }}</strong><small>{{ $activity['detail'] }}</small></span>
                            <time datetime="{{ $activity['date']->toDateString() }}">{{ $activity['date']->format('d/m') }}</time>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <section class="dashboard-quick-links" aria-label="Accesos rápidos">
        <a href="{{ route('milk-production.create') }}"><span class="quick-link-icon quick-link-blue">◉</span><span><strong>Registrar ordeño</strong><small>Producción diaria</small></span><b aria-hidden="true">→</b></a>
        <a href="{{ route('reproduction.create') }}"><span class="quick-link-icon quick-link-rose">♡</span><span><strong>Evento reproductivo</strong><small>Celos, servicios y partos</small></span><b aria-hidden="true">→</b></a>
        <a href="{{ route('weights.create') }}"><span class="quick-link-icon quick-link-amber">↗</span><span><strong>Registrar pesaje</strong><small>Crecimiento del hato</small></span><b aria-hidden="true">→</b></a>
        <a href="{{ route('finance.transactions.create') }}"><span class="quick-link-icon quick-link-green">$</span><span><strong>Nuevo movimiento</strong><small>Ingresos y gastos</small></span><b aria-hidden="true">→</b></a>
    </section>

    <p class="dashboard-footer-note">Los indicadores se calculan con los registros actuales del sistema y la finca seleccionada.</p>
@endsection
