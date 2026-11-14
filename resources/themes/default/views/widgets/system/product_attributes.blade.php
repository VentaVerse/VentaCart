{{-- The product's specifications as a placeable piece, for a theme built on
     this one that declares a product_main region. --}}
@php $attributeGroups = \App\Support\PageContext::get('attributeGroups', []); @endphp
@if (! empty($attributeGroups))
    <h2 class="product-attributes__title">Specifications</h2>
    @include('product.partials.attributes')
@endif
