{{-- One page part, on or off.

     The switch, because the doctrine has exactly one boolean-state control
     and this is boolean state. It was a bare checkbox, which is the admin's
     multi-selection control, so four of them in a box read as "pick the parts
     you want" rather than "each of these is currently on". The old rule also
     restyled the checkbox under a private class name, which is why it was the
     one control on this screen painting itself Chromium blue in a brass
     admin. Nothing is restyled here: this is ui.css. --}}
<label class="v-toggle v-lay__partsw">
    <input type="checkbox" :checked="f.value"
           @change="setPart(f, $event.target.checked)">
    <span class="v-toggle__track" aria-hidden="true"></span>
    <span x-text="f.label"></span>
</label>
