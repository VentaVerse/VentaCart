@props(['name', 'checked' => false])
<span class="v-toggle inline-flex">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="sr-only peer" {{ $attributes }}>
        <span class="w-9 h-5 rounded-full bg-v-strong peer-checked:bg-v-accent transition-colors"></span>
        <span class="absolute left-0.5 top-0.5 w-4 h-4 rounded-full bg-white shadow peer-checked:translate-x-4 transition-transform"></span>
    </label>
</span>
