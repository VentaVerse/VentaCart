@props(['field'])
{{-- One colour choice for a row: the theme's own colours as chips, or a hex
     of the merchant's. Bound to the open row's style through rs()/setRowStyle(). --}}
<span class="v-lay__chips" role="group">
    <button type="button" class="v-chip v-chip--none" :class="{ 'is-on': rs('{{ $field }}') === '' }"
            @click="setRowStyle('{{ $field }}', '')" aria-label="No colour" title="None"></button>
    <template x-for="c in themeColors" :key="c.key">
        <button type="button" class="v-chip" :class="{ 'is-on': rs('{{ $field }}') === c.key }"
                :style="'background:' + c.hex" @click="setRowStyle('{{ $field }}', c.key)"
                :aria-label="c.label" :title="c.label"></button>
    </template>
    <label class="v-chip v-chip--custom" :class="{ 'is-on': String(rs('{{ $field }}')).startsWith('#') }" title="Custom colour">
        <input type="color" :value="String(rs('{{ $field }}')).startsWith('#') ? rs('{{ $field }}') : '#888888'"
               @input="setRowStyle('{{ $field }}', $event.target.value.toUpperCase())" aria-label="Custom colour">
        <i :style="'background:' + swatch(rs('{{ $field }}'))"></i>
    </label>
</span>
