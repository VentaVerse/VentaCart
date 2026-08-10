{{-- The stepped pager: the span you are looking at on the left, the
     two steps on the right.

     For a list too long to number but short enough to walk. The
     numbered paginator (resources/views/vendor/pagination) is the
     other half of the same material and shares this file's classes.

     A step with nowhere to go is still a word a person reads, so it
     renders as a disabled control at full contrast rather than as a
     missing one. --}}
@props(['paginator'])
@if ($paginator->total() > 0)
    <div {{ $attributes->merge(['class' => 'v-pager mt-4']) }}>
        <span class="v-pager__count">{{ number_format($paginator->firstItem() ?? 0) }} to {{ number_format($paginator->lastItem() ?? 0) }} of {{ number_format($paginator->total()) }}</span>
        <div class="v-pager__nav">
            {{-- :href, never {{ }}: the component escapes the href once
                 itself, and an already-escaped query string sends the
                 literal param name "amp;sub" on click. --}}
            @if ($paginator->previousPageUrl())
                <x-admin.button size="sm" variant="secondary" :href="$paginator->previousPageUrl()">Previous</x-admin.button>
            @else
                <x-admin.button size="sm" variant="secondary" disabled>Previous</x-admin.button>
            @endif
            @if ($paginator->nextPageUrl())
                <x-admin.button size="sm" variant="secondary" :href="$paginator->nextPageUrl()">Next</x-admin.button>
            @else
                <x-admin.button size="sm" variant="secondary" disabled>Next</x-admin.button>
            @endif
        </div>
    </div>
@endif
