@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="flex items-center justify-between px-4 py-3 bg-white border-t border-border" aria-label="Pagination">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-text-light">
                    Mostrando <span class="font-medium">{{ $paginator->firstItem() }}</span> a <span class="font-medium">{{ $paginator->lastItem() }}</span> de <span class="font-medium">{{ $paginator->total() }}</span> resultados
                </p>
            </div>
            <div>
                <span class="relative z-0 inline-flex rounded-md shadow-sm">
                    @foreach ($paginator->linkCollection() as $link)
                        @if ($link['label'] === 'pagination.previous')
                            @if ($paginator->onFirstPage())
                                <span aria-disabled="true" aria-label="Anterior">
                                    <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-text-muted bg-white border border-border rounded-l-md cursor-default leading-5">&lsaquo;</span>
                                </span>
                            @else
                                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-text-light bg-white border border-border rounded-l-md hover:bg-surface-dark leading-5" aria-label="Anterior">&lsaquo;</a>
                            @endif
                        @elseif ($link['label'] === 'pagination.next')
                            @if ($paginator->hasMorePages())
                                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-text-light bg-white border border-border rounded-r-md hover:bg-surface-dark leading-5" aria-label="Siguiente">&rsaquo;</a>
                            @else
                                <span aria-disabled="true" aria-label="Siguiente">
                                    <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-text-muted bg-white border border-border rounded-r-md cursor-default leading-5">&rsaquo;</span>
                                </span>
                            @endif
                        @elseif ($link['label'] === '...')
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-text-muted bg-white border border-border cursor-default leading-5">...</span>
                            </span>
                        @else
                            @if ($link['active'])
                                <span aria-current="page">
                                    <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-white bg-primary border border-primary cursor-default leading-5">{!! $link['label'] !!}</span>
                                </span>
                            @else
                                <a href="{{ $link['url'] }}" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-text-light bg-white border border-border hover:bg-surface-dark leading-5" aria-label="Ir a página {{ $link['label'] }}">{!! $link['label'] !!}</a>
                            @endif
                        @endif
                    @endforeach
                </span>
            </div>
        </div>
    </nav>
@endif
