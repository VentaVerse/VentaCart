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
            {{-- Numbered pages, because Previous and Next over ninety-four
                 pages is not navigation, it is a corridor. Windowed around
                 the current page with the two ends always reachable, so the
                 control stays one line at any total. Each href is echoed ONCE
                 for the same reason the steps are; see above. --}}
            @php
                $vLast = $paginator->lastPage();
                $vNow = $paginator->currentPage();
                $vWindow = collect(range(max(1, $vNow - 1), min($vLast, $vNow + 1)))
                    ->prepend(1)->push($vLast)->unique()->sort()->values();
            @endphp
            @if ($vLast > 1)
                <span class="v-pager__pages">
                    @php $vPrev = 0; @endphp
                    @foreach ($vWindow as $vPage)
                        @if ($vPrev && $vPage > $vPrev + 1)
                            <span class="v-pager__gap" aria-hidden="true">&hellip;</span>
                        @endif
                        @if ($vPage === $vNow)
                            <span class="v-pager__n is-on" aria-current="page">{{ $vPage }}</span>
                        @else
                            <a href="{{ $paginator->url($vPage) }}" class="v-pager__n" aria-label="Page {{ $vPage }}">{{ $vPage }}</a>
                        @endif
                        @php $vPrev = $vPage; @endphp
                    @endforeach
                </span>
            @endif
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
