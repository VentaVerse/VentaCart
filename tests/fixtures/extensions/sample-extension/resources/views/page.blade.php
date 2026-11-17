{{-- The extension's own page, for a theme that has no page view of its own. --}}
<x-store-layout :title="$title">
    <h1 data-sample-page="fallback">{{ $thing->name }}</h1>
</x-store-layout>
