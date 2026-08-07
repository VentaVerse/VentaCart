@props(['align' => 'left', 'mono' => false])
<td {{ $attributes->merge(['class' => 'px-4 py-2 align-middle ' . ($align === 'right' ? 'text-right ' : '') . ($mono ? 'v-num ' : '')]) }}>{{ $slot }}</td>
