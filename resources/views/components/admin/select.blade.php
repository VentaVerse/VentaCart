@props(['name', 'value' => null])
<select name="{{ $name }}" id="{{ $name }}"
    {{ $attributes->merge(['class' => 'v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border rounded-v-md '
        . ($errors->has($name) ? 'border-v-danger' : 'border-v-border')]) }}>
    {{ $slot }}
</select>
