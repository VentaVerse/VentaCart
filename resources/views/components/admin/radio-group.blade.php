@props(['name', 'options' => [], 'value' => null])
<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @foreach ($options as $key => $label)
        <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
            <input type="radio" name="{{ $name }}" value="{{ $key }}" @checked((string) old($name, $value) === (string) $key)
                class="w-4 h-4 text-v-accent focus:ring-v-accent bg-v-surface">
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
