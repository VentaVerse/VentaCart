{{-- The box edge comes from components/forms.css, which states it for
     every checkbox in the admin including the raw ones in row
     editors, so the unchecked box clears 3:1 against the sheet. --}}
@props(['name', 'checked' => false, 'label' => null, 'value' => 1])
{{-- `.v-check` is the target. The box stays 16px; the label that
     wraps it is 24px tall, and 24px wide too when there are no words
     to widen it, so a bare box in a permissions grid still clears
     2.5.8's floor without growing on the page. --}}
<label class="v-check @if (! $label) v-check--bare @endif">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
        {{ $attributes->merge(['class' => 'w-4 h-4 rounded-v-sm text-v-accent focus:ring-v-accent bg-v-surface']) }}>
    @if ($label)<span>{{ $label }}</span>@endif
</label>
