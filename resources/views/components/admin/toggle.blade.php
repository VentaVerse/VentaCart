{{-- The visible switch is the track, not the input: the input is
     visually hidden, so the track carries the colours and the focus
     ring. Both live in components/forms.css, where the off track
     takes the control edge stop and the knob takes the sheet colour
     off and the accent's text colour on, which is what keeps the knob
     visible against a pale brass track in dark theme. --}}
@props(['name', 'checked' => false])
<span class="v-toggle inline-flex">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" name="{{ $name }}" id="{{ $name }}" value="1" @checked(old($name, $checked))
            {{ $attributes->merge(['class' => 'sr-only peer']) }}>
        <span class="v-toggle__track w-9 h-5 rounded-full peer-checked:bg-v-accent transition-colors"></span>
        <span class="v-toggle__knob absolute left-0.5 top-0.5 w-4 h-4 rounded-full shadow peer-checked:translate-x-4 transition-transform"></span>
    </label>
</span>
