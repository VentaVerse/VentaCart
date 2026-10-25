{{-- The wishlist page. Core's own, drawn inside the live theme's layout
     with the theme's product card; a theme may ship wishlist/index to
     draw it its own way. --}}
<x-store-layout title="Wishlist">
    <div class="container-store wishlist-page">
        <h1 class="wishlist-page__title">Wishlist</h1>
        @if ($products->isEmpty())
            <p class="wishlist-page__empty">Nothing saved yet. Tap the heart on a product to keep it here.</p>
            <p><a href="{{ route('home') }}" class="wishlist-page__link">Continue shopping</a></p>
        @else
            <div class="wishlist-page__grid">
                @foreach ($products as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </div>
</x-store-layout>
