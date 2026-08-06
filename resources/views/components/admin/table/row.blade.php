@props(['href' => null])
<tr {{ $attributes->merge(['class' => 'v-row ' . ($href ? 'relative cursor-pointer' : '')]) }} @if($href) data-href="{{ $href }}" tabindex="0" role="link" @endif>
    {{ $slot }}
</tr>
