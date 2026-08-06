@props(['name', 'checked' => false, 'label' => null, 'value' => 1])
<label class="inline-flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
        {{ $attributes->merge(['class' => 'w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface']) }}>
    @if ($label)<span>{{ $label }}</span>@endif
</label>
