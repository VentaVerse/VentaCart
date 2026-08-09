{{-- The resting edge is not written here. It is the one border in the
     system WCAG 1.4.11 applies to, so it takes its own token
     (--border-control) from components/forms.css rather than the
     divider stop the panels use.

     `help`, `hint` and `errorKey` are x-admin.field's props and no
     other component's, so @aware reaching up for them can only resolve
     to the field this control sits inside. That is how the control
     learns which hint and which error are describing it without any of
     the 288 callers repeating an id. --}}
@props(['name', 'type' => 'text', 'value' => null])
@aware(['help' => null, 'hint' => null, 'errorKey' => null])
@php
    $vErrorKey = $errorKey ?: $name;
    $vInvalid = isset($errors) && $errors->has($vErrorKey);
    $vDescribedBy = implode(' ', array_filter([
        ($hint ?? $help) ? $name . '-hint' : null,
        $vInvalid ? $vErrorKey . '-error' : null,
    ]));
@endphp
<input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}"
    {{ $attributes->merge(array_filter([
        'class' => 'v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted'
            . ($vInvalid ? ' v-control--error' : ''),
        'aria-describedby' => $vDescribedBy ?: null,
        'aria-invalid' => $vInvalid ? 'true' : null,
    ])) }}>
