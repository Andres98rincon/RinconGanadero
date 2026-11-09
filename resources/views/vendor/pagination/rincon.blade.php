{{--
    Paginación compacta: primera, anterior/siguiente a la actual y última página, con "…" entre saltos.
    Nunca se desborda ni se parte en varias líneas; en celular muestra "página / total".
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect([1, $current - 1, $current, $current + 1, $last])
            ->filter(fn (int $page): bool => $page >= 1 && $page <= $last)
            ->unique()->sort()->values();
    @endphp
    <nav class="pager" role="navigation" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="pager-step is-disabled" aria-disabled="true" aria-label="Página anterior">‹</span>
        @else
            <a class="pager-step" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior">‹</a>
        @endif

        <span class="pager-pages">
            @foreach ($pages as $index => $page)
                @if ($index > 0 && $page - $pages[$index - 1] > 1)
                    <span class="pager-gap" aria-hidden="true">…</span>
                @endif
                @if ($page === $current)
                    <span aria-current="page"><span>{{ $page }}</span></span>
                @else
                    <a href="{{ $paginator->url($page) }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                @endif
            @endforeach
        </span>

        <span class="pager-compact" aria-live="polite">{{ $current }} / {{ $last }}</span>

        @if ($paginator->hasMorePages())
            <a class="pager-step" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente">›</a>
        @else
            <span class="pager-step is-disabled" aria-disabled="true" aria-label="Página siguiente">›</span>
        @endif
    </nav>

    @once
        <style>
            .pagination { flex-wrap: wrap; row-gap: 10px; }
            .pagination > span:first-child { min-width: 0; }
            .pagination nav.pager { display: flex; flex: 0 1 auto; flex-wrap: nowrap; align-items: center; gap: 5px; min-width: 0; max-width: 100%; margin-left: auto; }
            .pager .pager-pages { display: flex; flex-wrap: nowrap; align-items: center; gap: 5px; min-width: 0; min-height: 0; padding: 0; border: 0; background: transparent; }
            .pager a, .pager .pager-step, .pager [aria-current="page"] span { flex: 0 0 auto; white-space: nowrap; }
            .pager .pager-step { font-size: 16px; line-height: 1; }
            .pager .pager-step.is-disabled { opacity: .4; }
            .pager .pager-gap, .pager .pager-compact { min-width: auto; min-height: 0; padding: 0 2px; border: 0; background: transparent; color: var(--muted, #77847a); }
            .pager [aria-current="page"] { display: contents; }
            .pager .pager-compact { display: none; }
            @media (max-width: 560px) {
                .pagination { justify-content: center; text-align: center; }
                .pagination nav.pager { margin: 0 auto; }
                .pager .pager-pages { display: none; }
                .pager .pager-compact { display: inline-flex; align-items: center; padding: 0 10px; color: inherit; font-weight: 700; }
            }
        </style>
    @endonce
@endif
