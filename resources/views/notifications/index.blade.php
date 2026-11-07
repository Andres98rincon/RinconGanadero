@extends('layouts.app')

@section('title', 'Notificaciones')

@section('content')
<div class="page-head"><div><div class="eyebrow">Seguimiento de la finca</div><h1>Notificaciones y recordatorios</h1><p class="page-subtitle">Recibe avisos de eventos ganaderos próximos y consulta su historial.</p></div></div>
<section class="notification-settings panel">
    <div><h2>Preferencias</h2><p class="page-subtitle">Configura con cuánta anticipación quieres recibir avisos.</p></div>
    <form method="POST" action="{{ route('notifications.preferences.update') }}" class="notification-preferences-form">@csrf @method('PUT')
        <label class="toggle-field"><input type="checkbox" name="enabled" value="1" @checked($preference->enabled)> <span>Notificaciones activas</span></label>
        <label for="lead_days">Avisar con anticipación</label><select class="control" id="lead_days" name="lead_days"><option value="0" @selected($preference->lead_days === 0)>El mismo día</option><option value="1" @selected($preference->lead_days === 1)>1 día antes</option><option value="3" @selected($preference->lead_days === 3)>3 días antes</option><option value="7" @selected($preference->lead_days === 7)>1 semana antes</option><option value="14" @selected($preference->lead_days === 14)>2 semanas antes</option></select>
        <fieldset><legend>Tipos de aviso</legend><div class="notification-category-grid">@foreach ($categories as $value => $label)<label><input type="checkbox" name="categories[]" value="{{ $value }}" @checked($preference->categories === null || in_array($value, $preference->categories, true))> {{ $label }}</label>@endforeach</div></fieldset>
        <button class="button button-primary" type="submit">Guardar preferencias</button>
    </form>
    <div class="push-control"><strong>Notificaciones en el dispositivo</strong><p data-push-status>Activa los avisos del navegador para recibir recordatorios aunque no tengas la pantalla abierta.</p><button class="button button-secondary" type="button" data-push-toggle data-push-public-key="{{ config('services.notifications.vapid_public_key') }}">Solicitar autorización</button></div>
</section>
<section class="table-wrap notification-history" aria-label="Historial de notificaciones"><div class="farm-animals-heading"><div><h2>Historial</h2><p>Los avisos están relacionados con el usuario y la finca de origen.</p></div></div>@if ($notifications->isEmpty())<div class="empty-state"><strong>No hay notificaciones.</strong>Cuando haya un evento próximo aparecerá aquí.</div>@else<table class="data-table"><thead><tr><th>Fecha</th><th>Asunto</th><th>Detalle</th><th>Estado</th></tr></thead><tbody>@foreach ($notifications as $notification) @php($data = $notification->data)<tr><td>{{ $notification->created_at->format('d/m/Y H:i') }}</td><td><strong>{{ $data['title'] ?? 'Recordatorio ganadero' }}</strong><span class="animal-sub">{{ $data['date'] ?? '' }}</span></td><td>{{ $data['body'] ?? '—' }}</td><td>{{ $notification->read_at ? 'Leída' : 'Pendiente' }}</td></tr>@endforeach</tbody></table><div class="pagination">{{ $notifications->links() }}</div>@endif</section>
@endsection
