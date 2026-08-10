{{-- The same switch a checkbox drives, driven by a button instead:
     one that posts over fetch, or submits a form of its own, or
     flips a row in place. Four settings screens wrote this pill in
     utilities; it is one material with the toggle, so it wears the
     toggle's track and knob and only the driver differs.

     The state lives on `aria-checked`, which is both what the CSS
     reads and what a screen reader announces, so the two can never
     disagree. `label` is the accessible name, because a 36px pill
     has no words of its own.

     Pass `:checked="null"` when the caller binds `:aria-checked`
     itself, so the two do not both write the attribute. --}}
@props(['checked' => false, 'label' => null])
<button type="{{ $attributes->get('type', 'button') }}" role="switch"
    {{ $attributes->except('type')->merge(array_filter([
        'class' => 'v-toggle v-toggle--btn',
        'aria-checked' => $checked === null ? null : ($checked ? 'true' : 'false'),
        'aria-label' => $label,
    ])) }}>
    <span class="v-toggle__track w-9 h-5 rounded-full transition-colors"></span>
    <span class="v-toggle__knob absolute left-0.5 top-0.5 w-4 h-4 rounded-full shadow transition-transform"></span>
</button>
