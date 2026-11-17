{{-- The home page: its template's sections, drawn inside the store frame. --}}
<x-store-layout>
    <h1 class="visually-hidden">{{ \App\Support\StoreIdentity::name() }}</h1>
    @include('xelga::snippets.template', ['pageType' => 'home'])
</x-store-layout>
