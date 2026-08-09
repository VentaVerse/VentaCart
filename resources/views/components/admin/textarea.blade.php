{{-- See input.blade.php for why @aware is how the hint and the error
     reach the control. --}}
@props(['name', 'rows' => 4, 'value' => null])
@aware(['help' => null, 'hint' => null, 'errorKey' => null])
@php
    $vErrorKey = $errorKey ?: $name;
    $vInvalid = isset($errors) && $errors->has($vErrorKey);
    $vDescribedBy = implode(' ', array_filter([
        ($hint ?? $help) ? $name . '-hint' : null,
        $vInvalid ? $vErrorKey . '-error' : null,
    ]));
@endphp
<textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}"
    {{ $attributes->merge(array_filter([
        'class' => 'v-control w-full px-2.5 py-2 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted'
            . ($vInvalid ? ' v-control--error' : ''),
        'aria-describedby' => $vDescribedBy ?: null,
        'aria-invalid' => $vInvalid ? 'true' : null,
    ])) }}>{{ old($name, $value) }}</textarea>
