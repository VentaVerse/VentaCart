@props(['name', 'label' => null, 'required' => false, 'hint' => null])
{{-- A hint never prints on the page: it lives behind the label's
     question mark (owner directive: no instructional text in the
     UI). Label-less fields keep the quiet line as a fallback. --}}
<label class="v-field">
    @if ($label !== null)
        <span class="v-field__label">
            {{ $label }}
            @if ($required)<span class="v-req">Required</span>@endif
            @if ($hint)<x-admin.help :text="$hint" />@endif
        </span>
    @endif
    {{ $slot }}
    @if ($hint && $label === null)
        <span class="v-field__hint">{{ $hint }}</span>
    @endif
    @error($name)
        <span class="v-field__error">{{ $message }}</span>
    @enderror
</label>
