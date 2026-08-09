{{-- The resting edge is not written here. It is the one border in the
     system WCAG 1.4.11 applies to, so it takes its own token
     (--border-control) from components/forms.css rather than the
     divider stop the panels use. --}}
@props(['name', 'type' => 'text', 'value' => null])
<input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}"
    {{ $attributes->merge(['class' => 'v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted'
        . ($errors->has($name) ? ' v-control--error' : '')]) }}>
