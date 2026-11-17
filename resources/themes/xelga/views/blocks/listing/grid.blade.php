{{-- The page of products core chose, drawn with the shared product card. --}}
<ul class="product-grid" aria-label="{{ $label }}" data-grid>
    @foreach ($data['list']['cards'] as $card)
        <li>@include('xelga::snippets.product-card', ['card' => $card, 'idPrefix' => $section['id']])</li>
    @endforeach
</ul>
