@props([
    'name',
    'label',
    'currentUrl' => null,
    'alt' => 'Vista previa de la imagen',
    'removeName' => null,
    'removeLabel' => 'Eliminar imagen actual',
])

<div class="field field-full image-upload" data-image-upload>
    <label for="{{ $name }}">{{ $label }} <span>JPG, PNG o WEBP · máximo 5 MB</span></label>
    <div class="image-upload-preview">
        <img
            class="image-preview"
            data-image-preview
            data-existing-src="{{ $currentUrl }}"
            src="{{ $currentUrl }}"
            alt="{{ $alt }}"
            @if (! $currentUrl) hidden @endif
        >
        <span class="image-upload-placeholder" data-image-placeholder @if ($currentUrl) hidden @endif>Selecciona una imagen para ver la vista previa</span>
    </div>
    <div class="image-upload-controls">
        <input
            class="control image-upload-input"
            id="{{ $name }}"
            name="{{ $name }}"
            type="file"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            data-image-input
            data-max-size="5120"
        >
        <button class="button button-secondary button-small" type="button" data-clear-image hidden>Quitar selección</button>
    </div>
    <span class="image-upload-progress" data-upload-progress hidden role="status">Guardando imagen…</span>
    @error($name)
        <span class="field-error">{{ $message }}</span>
    @enderror
    @if ($removeName && $currentUrl)
        <label class="photo-remove-option"><input type="checkbox" name="{{ $removeName }}" value="1" @checked(old($removeName))> {{ $removeLabel }}</label>
    @endif
</div>
