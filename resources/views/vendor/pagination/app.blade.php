@once
<style>
    .app-pg { display:flex;justify-content:center;align-items:center;gap:.3rem;flex-wrap:wrap;margin:1.5rem 0 .5rem; }
    .app-pg a, .app-pg span { display:inline-flex;align-items:center;justify-content:center;min-width:2rem;height:2rem;padding:0 .55rem;border-radius:.5rem;font-size:.82rem;font-weight:600;text-decoration:none;box-sizing:border-box; }
    .app-pg a { color:#334155;background:#fff;border:1px solid #e2e8f0; }
    .app-pg a:hover { background:#f8fafc;border-color:#cbd5e1; }
    .app-pg .app-pg-current { color:#fff;background:linear-gradient(135deg,#fcd34d 0%,#f59e0b 100%);border:1px solid #f59e0b; }
    .app-pg .app-pg-disabled { color:#cbd5e1;background:#fff;border:1px solid #f1f5f9;cursor:default; }
    .app-pg .app-pg-dots { color:#94a3b8;background:transparent;border:none;min-width:1rem; }
</style>
@endonce

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginering" class="app-pg">
        {{-- Vorige --}}
        @if ($paginator->onFirstPage())
            <span class="app-pg-disabled" aria-disabled="true">‹ Vorige</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Vorige</a>
        @endif

        {{-- Paginanummers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="app-pg-dots">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="app-pg-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Volgende --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Volgende ›</a>
        @else
            <span class="app-pg-disabled" aria-disabled="true">Volgende ›</span>
        @endif
    </nav>
@endif
