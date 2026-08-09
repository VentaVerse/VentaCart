{{-- Label above the control, the required tag beside the label, the
     hint under the control and the error under everything, directly
     beneath the control it belongs to. Never a summary at the top of
     a form, which makes a person hunt for the field a message is
     about.

     Props are additive: `name`, `label` and `help` are unchanged, so
     no existing caller needs editing. `required` prints the tag,
     `hint` reads as an alias of `help`, and `errorKey` lets a field
     show a message that lives under a different name (a typeahead
     whose visible input and stored id are two different keys). --}}
@props([
    'name',
    'label' => null,
    'help' => null,
    'hint' => null,
    'required' => false,
    'errorKey' => null,
])
@php
$prose = $hint ?? $help;
$key = $errorKey ?? $name;
$message = $key ? $errors->first($key) : null;

// The control already takes `id="{$name}"` from x-admin.input/select/
// textarea/toggle, which is what makes `for` above resolve. The hint
// and the error extend that one convention rather than starting a
// second: "{$name}-hint" and "{$key}-error". The controls read the same
// two props back out with @aware and point aria-describedby at them, so
// none of the 288 callers changes.
$hintId = $name . '-hint';
$errorId = $key . '-error';
@endphp
<div {{ $attributes->merge(['class' => 'v-field']) }}>
    @if ($label)
        <label for="{{ $name }}" class="v-field__label v-label">
            {{ $label }}
            @if ($required)<span class="v-req">Required</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($prose)<div id="{{ $hintId }}" class="v-field__hint">{{ $prose }}</div>@endif

    @if ($message)<div id="{{ $errorId }}" class="v-field__error">{{ $message }}</div>@endif
</div>
