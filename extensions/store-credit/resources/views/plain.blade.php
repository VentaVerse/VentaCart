{{-- The extension's own frame, for a theme without extension frames. --}}
<x-store-layout :title="trim($__env->yieldContent('title'))">
    <h1>@yield('title')</h1>
    @yield('content')
</x-store-layout>
