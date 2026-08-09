{{-- The box edge comes from components/forms.css, which states it for
     every checkbox in the admin including the raw ones in row
     editors, so the unchecked box clears 3:1 against the sheet. --}}
@props(['name', 'checked' => false, 'label' => null, 'value' => 1])
<label class="inline-flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
        {{ $attributes->merge(['class' => 'w-4 h-4 rounded-v-sm text-v-accent focus:ring-v-accent bg-v-surface']) }}>
    @if ($label)<span>{{ $label }}</span>@endif
</label>
