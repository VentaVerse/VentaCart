{{-- A thing with a picture, a name and its identifiers.

     The image is an operational control, not decoration: a packer
     verifies the physical item against it, so it holds a real size
     and never becomes a thumbnail. The name clamps to two lines. The
     SKU and the variant are identification data, not fine print. --}}
@props(['name', 'image' => null, 'sku' => null, 'variant' => null])
<div {{ $attributes->merge(['class' => 'v-item']) }}>
    <div class="v-item__thumb">
        @if ($image)
            <img class="v-item__img" src="{{ $image }}" alt="">
        @else
            <span class="v-item__none">No image</span>
        @endif
    </div>
    <div class="v-item__body">
        <span class="v-item__name">{{ $name }}</span>
        @if ($sku || $variant)
            <div class="v-item__meta">
                @if ($sku)<span class="v-item__sku">{{ $sku }}</span>@endif
                @if ($variant)<span class="v-item__var">{{ $variant }}</span>@endif
            </div>
        @endif
    </div>
</div>
