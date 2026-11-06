@extends('layouts.app')

@section('title', 'Copias de seguridad')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administración del sistema</div>
            <h1>Copias de seguridad</h1>
            <p class="page-subtitle">Respaldos de base de datos y archivos, incluyendo fotos de animales y perfiles.</p>
        </div>
        <form method="POST" action="{{ route('admin.backups.store') }}" data-confirm="El respaldo puede tardar varios minutos. ¿Deseas iniciarlo ahora?">
            @csrf
            <button class="button button-primary" type="submit">Generar respaldo</button>
        </form>
    </div>

    <section class="stats-grid backup-destinations" aria-label="Estado de destinos de respaldo">
        @foreach ($destinations as $destination)
            <div class="stat">
                <span class="stat-label">Destino {{ $destination['name'] }}</span>
                <strong class="stat-value">{{ $destination['reachable'] ? 'Disponible' : 'No disponible' }}</strong>
            </div>
        @endforeach
    </section>

    <section class="table-wrap" aria-label="Historial de respaldos">
        @if ($backups === [])
            <div class="empty-state"><strong>Aún no hay respaldos.</strong>Genera uno desde esta página o espera la tarea programada.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Fecha y hora</th><th>Archivo</th><th>Tamaño</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr>
                            <td>{{ $backup['date']->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $backup['file'] }}</td>
                            <td>{{ Illuminate\Support\Number::fileSize($backup['size']) }}</td>
                            <td><span class="tag tag-active">Disponible</span></td>
                            <td><a class="button button-secondary button-small" href="{{ route('admin.backups.download', $backup['id']) }}">Descargar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
