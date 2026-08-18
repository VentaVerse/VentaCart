@props(['paginator'])
{{-- The stepped pager: the range you are looking at on the left, the two
     steps on the right. Each href is echoed ONCE ({{ }}) — the single
     escape a browser undoes on click. Routing it through a component
     prop would escape it a second time and send the literal "amp;sub"
     as a param name (the double-escape bug FulfillmentQueueTest pins). --}}
@if ($paginator->total() > 0)
    <nav {{ $attributes->merge(['class' => 'v-pager']) }} role="navigation" aria-label="Pagination">
        <span class="v-mono">{{ number_format($paginator->firstItem() ?? 0) }} to {{ number_format($paginator->lastItem() ?? 0) }} of {{ number_format($paginator->total()) }}</span>
        <span class="v-pager__steps">
            @if ($paginator->previousPageUrl())
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="v-btn v-btn--secondary v-btn--sm">Previous</a>
            @else
                <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">Previous</span>
            @endif
            @if ($paginator->nextPageUrl())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="v-btn v-btn--secondary v-btn--sm">Next</a>
            @else
                <span class="v-btn v-btn--secondary v-btn--sm" aria-disabled="true">Next</span>
            @endif
        </span>
    </nav>
@endif
