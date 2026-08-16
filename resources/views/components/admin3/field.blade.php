@props(['name', 'label' => null, 'required' => false, 'hint' => null])
{{-- A hint never prints on the page: it lives behind the label's
     question mark (owner directive: no instructional text in the
     UI). Label-less fields keep the quiet line as a fallback. --}}
<label class="v3-field">
    @if ($label !== null)
        <span class="v3-field__label">
            {{ $label }}
            @if ($required)<span class="v3-req">Required</span>@endif
            @if ($hint)<x-admin3.help :text="$hint" />@endif
        </span>
    @endif
    {{ $slot }}
    @if ($hint && $label === null)
        <span class="v3-field__hint">{{ $hint }}</span>
    @endif
    @error($name)
        <span class="v3-field__error">{{ $message }}</span>
    @enderror
</label>
