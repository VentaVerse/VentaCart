{{-- The ids are deterministic from the tab's own name, which is what
     lets the tab and its panel point at each other across two
     components that share only an Alpine scope. One tab set per page
     is the rule everywhere it is used today (verified by grep), so a
     name is unique enough to key on.

     Left and Right move between tabs and select as they go, which is
     the APG's automatic-activation pattern and what a person who has
     used a tab set anywhere else will try. The nav slot holds nothing
     but tabs, so element siblings are tabs. --}}
@props(['name'])
<button type="button" role="tab" @click="pick('{{ $name }}')"
        id="v-tab-{{ $name }}" aria-controls="v-tabpanel-{{ $name }}"
        @keydown.arrow-right.prevent="$el.nextElementSibling && ($el.nextElementSibling.focus(), $el.nextElementSibling.click())"
        @keydown.arrow-left.prevent="$el.previousElementSibling && ($el.previousElementSibling.focus(), $el.previousElementSibling.click())"
        :aria-selected="tab === '{{ $name }}' ? 'true' : 'false'"
        :class="tab === '{{ $name }}'
            ? 'border-v-accent text-v-accent'
            : 'border-transparent text-v-secondary hover:text-v-text'"
        {{ $attributes->merge(['class' => '-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2 text-[13px] font-medium transition-colors']) }}>
    {{ $slot }}
</button>
