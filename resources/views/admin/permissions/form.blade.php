@extends('layouts.app')

@section('title', 'Crear permiso')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.permissions.index') }}">Permisos</a> / Nuevo permiso</div>
            <h1>Crear permiso</h1>
            <p class="page-subtitle">Define una capacidad para asignarla a roles personalizados.</p>
        </div>
    </div>

    <form class="form-layout" method="POST" action="{{ route('admin.permissions.store') }}">
        @csrf
        <section class="form-panel admin-form-panel">
            <div class="section-heading">
                <h2>Identificador de permiso</h2>
                <p>Usa el formato módulo.acción, por ejemplo inventario.consultar.</p>
            </div>
            <div class="field">
                <label for="name">Nombre técnico *</label>
                <input class="control" id="name" name="name" value="{{ old('name') }}" placeholder="modulo.accion" maxlength="100" pattern="[a-z][a-z0-9-]*\.[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)*" required>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="role-notice permission-note">Crear el permiso no lo conecta automáticamente a una acción del sistema. Asígnalo a un rol y úsalo en una policy o gate cuando implementes esa función.</div>
            <div class="form-actions">
                <a class="button button-secondary" href="{{ route('admin.permissions.index') }}">Cancelar</a>
                <button class="button button-primary" type="submit">Crear permiso</button>
            </div>
        </section>
        <aside class="side-panel">
            <h3>Nomenclatura</h3>
            <p>Los permisos existentes usan identificadores cortos y descriptivos, como <strong>animals.update</strong> o <strong>reports.export</strong>.</p>
            <p>Un permiso puede eliminarse solo cuando ya no está asignado a usuarios ni a roles.</p>
        </aside>
    </form>
@endsection
