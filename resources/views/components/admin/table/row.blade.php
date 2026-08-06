@props(['href' => null])
<tr {{ $attributes->merge(['class' => 'v-row ' . ($href ? 'relative cursor-pointer' : '')]) }} @if($href) data-href="{{ $href }}" @endif>
    {{ $slot }}
</tr>
