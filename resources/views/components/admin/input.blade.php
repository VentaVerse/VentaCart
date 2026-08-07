@props(['name', 'type' => 'text', 'value' => null])
<input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}"
    {{ $attributes->merge(['class' => 'v-control w-full h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted '
        . ($errors->has($name) ? 'border-v-danger' : 'border-v-border')]) }}>
