@extends('layouts.app')

@section('title', 'Inventario animal')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Control de hacienda</div>
            <h1>Inventario animal</h1>
            <p class="page-subtitle">Identificación, estado y seguimiento de los animales de la finca.</p>
        </div>
        <div class="page-actions">
            @can('animals.export')
                <details class="action-menu">
                    <summary class="button button-secondary"><span aria-hidden="true">⇩</span> Exportar</summary>
                    <div class="action-menu-content">
                        <a href="{{ route('animals.export', ['format' => 'xlsx'] + request()->query()) }}" data-export>Excel (.xlsx)</a>
                        <a href="{{ route('animals.export', ['format' => 'csv'] + request()->query()) }}" data-export>CSV</a>
                    </div>
                </details>
            @endcan
            @can('create', App\Models\Animal::class)
                <a class="button button-primary" href="{{ route('animals.create') }}"><span aria-hidden="true">+</span> Registrar animal</a>
            @endcan
        </div>
    </div>

    <section class="animal-lookup-panel" aria-label="Consultar animal por código QR">
        <form class="animal-lookup-form" method="GET" action="{{ route('animals.lookup') }}">
            <div class="field">
                <label for="animal-qr-code">Buscar animal por código</label>
                <input class="control" id="animal-qr-code" name="code" type="search" maxlength="300" autocomplete="off" autocapitalize="characters" placeholder="Código de 16 caracteres o dirección del QR" required>
            </div>
            <button class="button button-secondary" type="submit">Buscar</button>
            <button class="button button-primary" type="button" data-qr-scan-open>Escanear con cámara</button>
        </form>
        <div class="qr-scanner" data-qr-scanner hidden>
            <div class="qr-scanner-frame">
                <video class="qr-scanner-video" data-qr-video autoplay playsinline muted aria-label="Vista de la cámara para escanear el código QR"></video>
                <span class="qr-scanner-target" aria-hidden="true"></span>
            </div>
            <p data-qr-scanner-status role="status">Apunta la cámara al código QR del animal.</p>
            <input class="qr-photo-input" type="file" accept="image/*" capture="environment" data-qr-photo-input tabindex="-1" aria-hidden="true">
            <button class="button button-primary" type="button" data-qr-photo-open hidden>Abrir cámara del celular</button>
            <button class="button button-secondary" type="button" data-qr-scan-close>Detener cámara</button>
        </div>
    </section>

    @can('animals.import')
        <details class="animal-import-panel">
            <summary class="animal-import-summary"><span><strong>Importar animales</strong><small>Excel o CSV</small></span><span aria-hidden="true">＋</span></summary>
            <form class="animal-import-form" method="POST" action="{{ route('animals.import') }}" enctype="multipart/form-data">
                @csrf
                <p>Usa la plantilla del sistema. Los identificadores y microchips duplicados se rechazan.</p>
                <div class="animal-import-controls">
                    <a class="button button-secondary button-small" href="{{ route('animals.import-template') }}" data-export>Descargar plantilla</a>
                    <label class="button button-secondary button-small import-file-label" for="animal-import-file">Seleccionar archivo</label>
                    <input class="visually-hidden" id="animal-import-file" name="file" type="file" accept=".xlsx,.csv" required>
                    <span class="import-file-name" data-import-file-name>Sin archivo seleccionado</span>
                    <button class="button button-primary button-small" type="submit">Importar</button>
                </div>
                @error('file')<span class="field-error field-full">{{ $message }}</span>@enderror
            </form>
        </details>
    @endcan

    @if (session('import_report'))
        @php($importReport = session('import_report'))
        <section class="import-report" aria-label="Resultado de la importación">
            <strong>Resultado: {{ $importReport['imported'] }} importados, {{ $importReport['failed'] }} filas rechazadas.</strong>
            @if ($importReport['errors'])
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Fila</th><th>Errores</th></tr></thead>
                        <tbody>
                            @foreach ($importReport['errors'] as $rowError)
                                <tr>
                                    <td>{{ $rowError['row'] }}</td>
                                    <td>{{ implode(' ', $rowError['errors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($importReport['failed'] > count($importReport['errors']))
                    <p>Se muestran las primeras {{ count($importReport['errors']) }} filas con errores.</p>
                @endif
            @endif
        </section>
    @endif

    <section class="stats-grid" aria-label="Resumen del inventario">
        <div class="stat"><span class="stat-label">Animales registrados</span><strong class="stat-value">{{ number_format($stats['total']) }}</strong></div>
        <div class="stat"><span class="stat-label">En la finca</span><strong class="stat-value">{{ number_format($stats['active']) }}</strong></div>
        <div class="stat"><span class="stat-label">Hembras activas</span><strong class="stat-value">{{ number_format($stats['females']) }}</strong></div>
        <div class="stat"><span class="stat-label">Nacimientos este mes</span><strong class="stat-value">{{ number_format($stats['births']) }}</strong></div>
    </section>

    <form class="filter-bar" method="GET" action="{{ route('animals.index') }}">
        <input class="control filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por arete, nombre o microchip" aria-label="Buscar animales">
        <select class="filter-control" name="species" aria-label="Filtrar por especie" data-searchable-select data-placeholder="Todas las especies" data-allow-clear="true">
            <option value="">Todas las especies</option>
            @foreach ($speciesOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['species'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="farm_id" aria-label="Filtrar por finca" data-searchable-select data-placeholder="Todas las fincas" data-allow-clear="true">
            <option value="">Todas las fincas</option>
            @foreach ($farms as $farm)
                <option value="{{ $farm->id }}" @selected((string) ($filters['farm_id'] ?? '') === (string) $farm->id)>{{ $farm->name }} · {{ $farm->code }}</option>
            @endforeach
        </select>
        <select class="filter-control" name="status" aria-label="Filtrar por estado" data-searchable-select data-placeholder="Todos los estados" data-allow-clear="true">
            <option value="">Todos los estados</option>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="button button-secondary" type="submit">Filtrar</button>
        @if ($filters)
            <a class="button button-secondary" href="{{ route('animals.index') }}">Limpiar</a>
        @endif
    </form>

    <section class="table-wrap" aria-label="Animales registrados">
        @if ($animals->isEmpty())
            <x-empty-state icon="🐄" :variant="$filters ? 'search' : 'module'" :title="$filters ? 'No encontramos animales' : '¡Tu inventario está vacío!'" :description="$filters ? 'No hay animales que coincidan con los filtros actuales.' : 'Aún no tienes animales registrados. Comienza agregando tu primer animal.'" :action-url="$filters ? null : route('animals.create')" :action-label="$filters ? null : 'Registrar primer animal'" />
        @else
            <table class="data-table">
                <thead>
                    <tr><th>Foto / identificación</th><th>Especie / raza</th><th>Finca / potrero</th><th>Sexo</th><th>Nacimiento</th><th>Peso actual</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    @foreach ($animals as $animal)
                        <tr>
                            <td>
                                <div class="animal-identity">
                                    @if ($animal->hasMedia('photo'))
                                        <img class="animal-avatar" src="{{ $animal->getFirstMedia('photo')?->getAvailableUrl(['optimized']) }}" alt="Foto de {{ $animal->ear_tag }}" loading="lazy">
                                    @else
                                        <span class="animal-avatar animal-avatar-empty" aria-hidden="true">🐄</span>
                                    @endif
                                    <div><a class="animal-link" href="{{ route('animals.show', $animal) }}">{{ $animal->ear_tag }}</a><span class="animal-sub">{{ $animal->name ?: 'Sin nombre' }}</span></div>
                                </div>
                            </td>
                            <td>{{ $speciesOptions[$animal->species] ?? 'Otro' }}<span class="animal-sub">{{ $animal->breed?->name ?? 'Raza sin especificar' }}</span></td>
                            <td>{{ $animal->farm?->name ?? 'Sin finca' }}<span class="animal-sub">{{ $animal->location ?: 'Sin potrero asignado' }}</span></td>
                            <td>{{ ['female' => 'Hembra', 'male' => 'Macho', 'unknown' => 'Sin definir'][$animal->sex] ?? 'Sin definir' }}</td>
                            <td>{{ $animal->birth_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $animal->current_weight_kg ? number_format((float) $animal->current_weight_kg, 1, ',', '.') . ' kg' : '—' }}</td>
                            <td><span class="tag {{ $animal->status === 'active' ? 'tag-active' : 'tag-muted' }}">{{ $statusOptions[$animal->status] ?? $animal->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <span>Mostrando {{ $animals->firstItem() }}–{{ $animals->lastItem() }} de {{ $animals->total() }}</span>
                {{ $animals->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
@endsection
