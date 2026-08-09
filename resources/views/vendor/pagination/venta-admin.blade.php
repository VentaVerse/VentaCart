{{-- The admin's paginator. Written in the v-* token set so it follows
     html[data-theme] like every other surface, which the stock view's
     Tailwind `dark:` variants could not: they key off
     prefers-color-scheme, so in dark theme the stock pager kept
     text-gray-700 on the near-black page at 1.73:1 and rendered a
     white control block on a #0A0F17 sheet.

     No colour is written here. Everything visual lives in
     resources/css/admin/components/pagination.css, against the same
     tokens the tables and buttons use, so the current page is stated
     in the ink-on-canvas grammar the segmented filters already use
     and the page controls take the control edge stop that clears 3:1.

     Chevrons come from x-admin.icon rather than the stock view's own
     hand-drawn SVGs, so the pager sits on the same 24 grid at the
     same 1.75 stroke as everything around it. --}}
@if ($paginator->hasPages())
    <nav class="v-pager" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <p class="v-pager__info">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span class="v-pager__figure">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="v-pager__figure">{{ $paginator->lastItem() }}</span>
            @else
                <span class="v-pager__figure">{{ $paginator->count() }}</span>
            @endif
            {!! __('of') !!}
            <span class="v-pager__figure">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="v-pager__list">
            @if ($paginator->onFirstPage())
                <span class="v-pager__step is-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                    <x-admin.icon name="chevron-left" class="w-4 h-4" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="v-pager__step" aria-label="{{ __('pagination.previous') }}">
                    <x-admin.icon name="chevron-left" class="w-4 h-4" />
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="v-pager__gap" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="v-pager__page is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="v-pager__page" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="v-pager__step" aria-label="{{ __('pagination.next') }}">
                    <x-admin.icon name="chevron-right" class="w-4 h-4" />
                </a>
            @else
                <span class="v-pager__step is-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                    <x-admin.icon name="chevron-right" class="w-4 h-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
