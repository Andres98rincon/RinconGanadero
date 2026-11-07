@props([
    'icon' => '◎',
    'title' => 'Sin registros',
    'description' => null,
    'actionUrl' => null,
    'actionLabel' => null,
    'variant' => 'default',
])

<div {{ $attributes->merge(['class' => 'empty-state empty-state--'.$variant]) }} role="status">
    <span class="empty-state-icon" aria-hidden="true">{{ $icon }}</span>
    <strong>{{ $title }}</strong>
    @if ($description)<p>{{ $description }}</p>@endif
    @if ($actionUrl && $actionLabel)<a class="button button-primary empty-state-action" href="{{ $actionUrl }}">{{ $actionLabel }}</a>@endif
</div>
