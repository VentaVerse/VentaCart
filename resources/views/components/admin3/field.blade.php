@props(['name', 'label' => null, 'required' => false, 'hint' => null])
<label class="v3-field">
    @if ($label !== null)
        <span class="v3-field__label">
            {{ $label }}
            @if ($required)<span class="v3-req">Required</span>@endif
        </span>
    @endif
    {{ $slot }}
    @if ($hint)
        <span class="v3-field__hint">{{ $hint }}</span>
    @endif
    @error($name)
        <span class="v3-field__error">{{ $message }}</span>
    @enderror
</label>
