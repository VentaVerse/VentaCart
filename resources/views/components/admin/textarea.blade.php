@props(['name', 'rows' => 4, 'value' => null])
<textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}"
    {{ $attributes->merge(['class' => 'v-control w-full px-2.5 py-2 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted'
        . ($errors->has($name) ? ' v-control--error' : '')]) }}>{{ old($name, $value) }}</textarea>
