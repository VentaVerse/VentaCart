@props(['name', 'label' => null, 'help' => null])
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label for="{{ $name }}" class="block text-[13px] font-medium text-v-text">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($help)
        <p class="text-xs text-v-muted">{{ $help }}</p>
    @endif
    @error($name)
        <p class="text-xs text-v-danger">{{ $message }}</p>
    @enderror
</div>
