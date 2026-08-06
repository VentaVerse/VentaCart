@props(['name'])
<button type="button" role="tab" @click="pick('{{ $name }}')"
        :aria-selected="tab === '{{ $name }}' ? 'true' : 'false'"
        :class="tab === '{{ $name }}'
            ? 'border-v-accent text-v-accent'
            : 'border-transparent text-v-secondary hover:text-v-text'"
        class="-mb-px border-b-2 px-3 py-2 text-[13px] font-medium transition-colors"
        {{ $attributes }}>
    {{ $slot }}
</button>
