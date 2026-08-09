{{-- See input.blade.php for why @aware is how the hint and the error
     reach the control. --}}
@props(['name', 'value' => null])
@aware(['help' => null, 'hint' => null, 'errorKey' => null])
@php
    $vErrorKey = $errorKey ?: $name;
    $vInvalid = isset($errors) && $errors->has($vErrorKey);
    $vDescribedBy = implode(' ', array_filter([
        ($hint ?? $help) ? $name . '-hint' : null,
        $vInvalid ? $vErrorKey . '-error' : null,
    ]));
@endphp
<select name="{{ $name }}" id="{{ $name }}"
    {{ $attributes->merge(array_filter([
        'class' => 'v-control w-full h-[var(--control-h)] px-2.5 py-0 text-[13px] leading-[normal] bg-v-surface text-v-text border rounded-v-md'
            . ($vInvalid ? ' v-control--error' : ''),
        'aria-describedby' => $vDescribedBy ?: null,
        'aria-invalid' => $vInvalid ? 'true' : null,
    ])) }}>
    {{ $slot }}
</select>
